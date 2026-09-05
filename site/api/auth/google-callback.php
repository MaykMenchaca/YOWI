<?php
declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../lib/Response.php';
require __DIR__ . '/../lib/Session.php';
require __DIR__ . '/../lib/RateLimit.php';
require __DIR__ . '/../lib/GoogleAuth.php';

// GET /api/auth/google-callback.php — Google redirige aquí con ?code=...&state=...
// (o ?error=... si el cliente canceló). Nunca se expone el detalle real de un fallo al
// usuario: siempre se redirige a login.html?google_error=1, y el detalle real queda en
// error_log (mismo patrón de catch-and-log que orders/create.php y otros endpoints).

function ds_google_redirect(string $to): void
{
    header('Location: ' . $to, true, 302);
    exit;
}

$failUrl = '/login.html?google_error=1';

if (!ds_google_enabled()) {
    ds_google_redirect($failUrl);
}

ds_session_start();
$expectedState = $_SESSION['google_oauth_state'] ?? null;
unset($_SESSION['google_oauth_state']); // un solo uso, se valide o no

$state = (string) ($_GET['state'] ?? '');
$code  = (string) ($_GET['code'] ?? '');

if ($expectedState === null || $state === '' || !hash_equals((string) $expectedState, $state)) {
    error_log('google-callback.php: state inválido o ausente');
    ds_google_redirect($failUrl);
}
if ($code === '') {
    // Google manda ?error=access_denied si el usuario cancela en el consent screen.
    error_log('google-callback.php: sin code, error=' . (string) ($_GET['error'] ?? ''));
    ds_google_redirect($failUrl);
}

ds_rate_limit_ip('google-callback', ds_client_ip(), 30, 15);

$cfg = ds_google_config();
$token = ds_http_post_form('https://oauth2.googleapis.com/token', [
    'code'          => $code,
    'client_id'     => $cfg['client_id'],
    'client_secret' => $cfg['client_secret'],
    'redirect_uri'  => ds_google_redirect_uri(),
    'grant_type'    => 'authorization_code',
]);

if (!$token || empty($token['id_token'])) {
    error_log('google-callback.php: intercambio de code fallido: ' . json_encode($token));
    ds_google_redirect($failUrl);
}

$payload = ds_decode_id_token((string) $token['id_token']);
if (!$payload) {
    error_log('google-callback.php: id_token no decodificable');
    ds_google_redirect($failUrl);
}

$aud           = (string) ($payload['aud'] ?? '');
$exp           = (int) ($payload['exp'] ?? 0);
$sub           = (string) ($payload['sub'] ?? '');
$email         = strtolower(trim((string) ($payload['email'] ?? '')));
$emailVerified = (bool) ($payload['email_verified'] ?? false);
$nombre        = trim((string) ($payload['name'] ?? ($payload['given_name'] ?? '')));

if ($aud === '' || !hash_equals($cfg['client_id'], $aud)) {
    error_log('google-callback.php: aud no coincide con GOOGLE_CLIENT_ID');
    ds_google_redirect($failUrl);
}
if ($exp <= time() || $sub === '' || $email === '' || !$emailVerified) {
    error_log('google-callback.php: id_token inválido/expirado o email no verificado');
    ds_google_redirect($failUrl);
}
if ($nombre === '') {
    $nombre = explode('@', $email)[0];
}

$pdo = ds_get_pdo();

try {
    $stmt = $pdo->prepare('SELECT id, google_id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $existing = $stmt->fetch();

    if ($existing) {
        $userId = (int) $existing['id'];
        // Vincular sin tocar password_hash existente. Mismo correo = misma cuenta: es
        // seguro porque Google solo manda email_verified=true cuando de verdad
        // confirmó que ese correo le pertenece a esta persona.
        if (empty($existing['google_id'])) {
            $pdo->prepare('UPDATE users SET google_id = ?, email_verified = 1 WHERE id = ?')
                ->execute([$sub, $userId]);
        }
    } else {
        // Cuenta nueva vía Google: sin contraseña propia (password_hash NULL) y sin
        // términos aceptados todavía (el flujo de Google no pasa por ese checkbox) —
        // cuenta.html bloquea con un aviso obligatorio hasta que los acepte.
        $insert = $pdo->prepare(
            'INSERT INTO users (nombre, email, password_hash, google_id, email_verified, terms_accepted_at)
             VALUES (?, ?, NULL, ?, 1, NULL)'
        );
        $insert->execute([$nombre, $email, $sub]);
        $userId = (int) $pdo->lastInsertId();
    }
} catch (Throwable $e) {
    error_log('google-callback.php DB: ' . $e->getMessage());
    ds_google_redirect($failUrl);
}

ds_login_user($userId);
ds_google_redirect('/cuenta.html');
