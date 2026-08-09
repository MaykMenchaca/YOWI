<?php
declare(strict_types=1);

require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../lib/Response.php';
require __DIR__ . '/../../lib/AdminSession.php';
require __DIR__ . '/../../lib/Totp.php';
require __DIR__ . '/../../lib/Recovery.php';
require __DIR__ . '/../../lib/RateLimit.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') ds_json_error('Método no permitido', 405);

$adminId = ds_require_admin(true); // hay que poder confirmar el enrolamiento ANTES de estar enrolado

$body = ds_read_json_body();
ds_admin_csrf_check($body['csrf_token'] ?? null);

ds_rate_limit_ip('2fa', ds_client_ip(), 10, 15);

$code = preg_replace('/\D+/', '', (string) ($body['code'] ?? ''));
if ($code === '') ds_json_error('Ingresa el código de tu app', 400);

// Re-pedir la contraseña al activar el 2FA: si la sesión ya está secuestrada (equipo
// desatendido, cookie robada), esto evita que el atacante enrole SU autenticador sin
// saber la contraseña real de la cuenta.
$password = (string) ($body['password'] ?? '');
if ($password === '') ds_json_error('Confirma tu contraseña para activar el 2FA', 400);

$pdo = ds_get_pdo();
$stmt = $pdo->prepare('SELECT password_hash, totp_secret, totp_enabled FROM admins WHERE id = ?');
$stmt->execute([$adminId]);
$admin = $stmt->fetch();
if (!$admin || $admin['totp_secret'] === null) {
    ds_json_error('Primero genera el 2FA', 400);
}

if (!password_verify($password, (string) $admin['password_hash'])) {
    ds_json_error('Contraseña incorrecta', 401);
}

if (!ds_totp_verify((string) $admin['totp_secret'], $code)) {
    ds_json_error('Código incorrecto. Revisa la hora de tu dispositivo e inténtalo de nuevo.', 400);
}

$upd = $pdo->prepare('UPDATE admins SET totp_enabled = 1 WHERE id = ?');
$upd->execute([$adminId]);

// Generar códigos de recuperación (se muestran UNA sola vez al activar).
$codes = ds_generate_recovery_codes($pdo, $adminId, 10);

ds_json_success(['enabled' => true, 'recovery_codes' => $codes]);
