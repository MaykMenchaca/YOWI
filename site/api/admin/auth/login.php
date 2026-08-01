<?php
declare(strict_types=1);

require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../lib/Response.php';
require __DIR__ . '/../../lib/AdminSession.php';
require __DIR__ . '/../../lib/Validate.php';
require __DIR__ . '/../../lib/RateLimit.php';
require __DIR__ . '/../../lib/Totp.php';

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

// Segundo factor (TOTP): si está activo, exigir un código válido antes de crear la sesión.
if ((int) $admin['totp_enabled'] === 1) {
    $totp = preg_replace('/\D+/', '', (string) ($body['totp'] ?? ''));
    if ($totp === '') {
        // Contraseña correcta pero falta el 2FA: NO se inicia sesión; el front pide el código.
        ds_json_success(['twofa_required' => true]);
    }
    if (!ds_totp_verify((string) $admin['totp_secret'], $totp)) {
        ds_login_record('admin', $email, $ip, false);
        ds_json_error('Código de verificación inválido', 401);
    }
}

ds_login_record('admin', $email, $ip, true);
ds_login_admin((int) $admin['id']);

ds_json_success([
    'id'     => (int) $admin['id'],
    'nombre' => $admin['nombre'],
    'email'  => $admin['email'],
]);
