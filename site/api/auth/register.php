<?php
declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../lib/Response.php';
require __DIR__ . '/../lib/Session.php';
require __DIR__ . '/../lib/Csrf.php';
require __DIR__ . '/../lib/Validate.php';
require __DIR__ . '/../lib/RateLimit.php';
require __DIR__ . '/../lib/Mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ds_json_error('Método no permitido', 405);
}

$body = ds_read_json_body();
ds_csrf_check($body['csrf_token'] ?? null);

// Anti-abuso: máx. 10 registros por IP cada 60 min (frena spam y enumeración masiva).
ds_rate_limit_ip('register', ds_client_ip(), 10, 60);

$nombre = ds_clean_string((string) ($body['nombre'] ?? ''), 150);
$email = ds_validate_email((string) ($body['email'] ?? ''));
$telefono = ds_clean_string((string) ($body['telefono'] ?? ''), 30);
$password = (string) ($body['password'] ?? '');
$confirmPassword = (string) ($body['confirm_password'] ?? '');
$terms = (bool) ($body['terms'] ?? false);

if ($nombre === '' || $email === null) {
    ds_json_error('Nombre o correo inválido', 400);
}
if (($errPass = ds_validate_password($password)) !== null) {
    ds_json_error($errPass, 400);
}
if ($password !== $confirmPassword) {
    ds_json_error('Las contraseñas no coinciden', 400);
}
// El checkbox del formulario ya bloquea el envío en el navegador (assets/js/auth.js),
// pero eso no evita un POST directo sin marcarlo — el control real es este, del lado
// del servidor. Sin esto, el consentimiento nunca quedaba registrado de verdad.
if (!$terms) {
    ds_json_error('Debes aceptar los términos y condiciones y el aviso de privacidad', 400);
}

$pdo = ds_get_pdo();

$check = $pdo->prepare('SELECT id FROM users WHERE email = ?');
$check->execute([$email]);
if ($check->fetch()) {
    ds_json_error('El correo ya está registrado', 409);
}

$hash = password_hash($password, PASSWORD_DEFAULT);

$insert = $pdo->prepare(
    'INSERT INTO users (nombre, email, telefono, password_hash, terms_accepted_at) VALUES (?, ?, ?, ?, NOW())'
);
$insert->execute([$nombre, $email, $telefono !== '' ? $telefono : null, $hash]);

$userId = (int) $pdo->lastInsertId();

// Si el correo está configurado, enviar verificación (no bloquea: la cuenta ya queda
// activa y logueada; la verificación es informativa hasta que se decida exigirla).
if (ds_email_enabled()) {
    try {
        [$plain, $tokenHash] = ds_make_token();
        $pdo->prepare(
            'INSERT INTO email_verifications (user_id, token_hash, expires_at) VALUES (?, ?, (NOW() + INTERVAL 24 HOUR))'
        )->execute([$userId, $tokenHash]);
        // Limpieza oportunista de tokens vencidos (~5% de las veces, mismo patrón que
        // ds_login_record() en lib/RateLimit.php): estos tokens se acumulaban para
        // siempre, incluso ya usados o caducados. No toca la tabla orders.
        if (random_int(1, 20) === 1) {
            $pdo->exec('DELETE FROM email_verifications WHERE expires_at < NOW()');
        }
        $base = ds_app_url() ?: '';
        $link = $base . '/api/auth/verify-email.php?token=' . $plain;
        $texto = "¡Bienvenido a Distribuidor de Suplementos!\n\n"
               . "Confirma tu correo con este enlace (válido 24 horas):\n{$link}\n";
        ds_send_mail($email, 'Confirma tu correo — Distribuidor de Suplementos', $texto);
    } catch (Throwable $e) {
        error_log('register.php verification email: ' . $e->getMessage());
    }
}

ds_login_user($userId);

ds_json_success(['id' => $userId, 'nombre' => $nombre, 'email' => $email], 201);
