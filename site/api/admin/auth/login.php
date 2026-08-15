<?php
declare(strict_types=1);

require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../lib/Response.php';
require __DIR__ . '/../../lib/AdminSession.php';
require __DIR__ . '/../../lib/Validate.php';
require __DIR__ . '/../../lib/RateLimit.php';
require __DIR__ . '/../../lib/Totp.php';
require __DIR__ . '/../../lib/Crypto.php';
require __DIR__ . '/../../lib/Recovery.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') ds_json_error('Método no permitido', 405);

// login.php es el único endpoint admin que NO requiere sesión previa.
// El CSRF se obtiene primero con GET /admin/auth/me.php y luego se envía aquí.
ds_admin_session_start();

$body = ds_read_json_body();
ds_admin_csrf_check($body['csrf_token'] ?? null);

$email = ds_validate_email((string)($body['email'] ?? ''));
$pass  = (string)($body['password'] ?? '');

if ($email === null || $pass === '') {
    ds_json_error('Email o contraseña inválidos', 400);
}

$ip = ds_client_ip();
ds_login_throttle_check('admin', $email, $ip);

$pdo  = ds_get_pdo();
$stmt = $pdo->prepare('SELECT id, nombre, email, password_hash, totp_secret, totp_enabled FROM admins WHERE email = ?');
$stmt->execute([$email]);
$admin = $stmt->fetch();

// Igualar el tiempo cuando el admin no existe (anti-timing-oracle).
if (!$admin) {
    ds_dummy_password_check();
    ds_login_record('admin', $email, $ip, false);
    ds_json_error('Credenciales incorrectas', 401);
}
if (!password_verify($pass, $admin['password_hash'])) {
    ds_login_record('admin', $email, $ip, false);
    ds_json_error('Credenciales incorrectas', 401);
}

// Segundo factor: si está activo, exigir un código TOTP válido o un código de
// recuperación de un solo uso antes de crear la sesión.
if ((int) $admin['totp_enabled'] === 1) {
    $factor = trim((string) ($body['totp'] ?? ''));
    if ($factor === '') {
        // Contraseña correcta pero falta el 2FA: NO se inicia sesión; el front pide el código.
        ds_json_success(['twofa_required' => true]);
    }
    $digits = preg_replace('/\D+/', '', $factor);
    $secretPlain = $admin['totp_secret'] !== null ? ds_decrypt((string) $admin['totp_secret']) : null;
    $totpOk = $digits !== '' && $secretPlain !== null && ds_totp_verify($secretPlain, $digits);
    // Si no es un TOTP válido, probar como código de recuperación (formato con guion/letras).
    $recoveryOk = !$totpOk && ds_consume_recovery_code($pdo, (int) $admin['id'], $factor);
    if (!$totpOk && !$recoveryOk) {
        ds_login_record('admin', $email, $ip, false);
        ds_json_error('Código de verificación inválido', 401);
    }
}

// Mantenimiento del hash, ya superados contraseña y 2FA (simétrico con auth/login.php):
// si el algoritmo/coste por defecto de PHP subió, re-hashear ahora que la contraseña en
// claro todavía existe. password_changed_at NO se toca a propósito: ds_admin_actual()
// (lib/AdminSession.php) cierra toda sesión anterior a esa marca, así que tocarla aquí
// expulsaría al admin de la sesión que acaba de abrir. El try/catch evita que un fallo
// de escritura impida un login por lo demás válido.
if (password_needs_rehash((string) $admin['password_hash'], PASSWORD_DEFAULT)) {
    try {
        $pdo->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($pass, PASSWORD_DEFAULT), (int) $admin['id']]);
    } catch (Throwable $e) {
        error_log('admin/login.php rehash: ' . $e->getMessage());
    }
}

ds_login_record('admin', $email, $ip, true);
ds_login_admin((int) $admin['id']);

ds_json_success([
    'id'     => (int) $admin['id'],
    'nombre' => $admin['nombre'],
    'email'  => $admin['email'],
]);
