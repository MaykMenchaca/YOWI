<?php
declare(strict_types=1);

require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../lib/Response.php';
require __DIR__ . '/../../lib/AdminSession.php';
require __DIR__ . '/../../lib/Totp.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') ds_json_error('Método no permitido', 405);

$adminId = ds_require_admin();

$body = ds_read_json_body();
ds_admin_csrf_check($body['csrf_token'] ?? null);

$code = preg_replace('/\D+/', '', (string) ($body['code'] ?? ''));
if ($code === '') ds_json_error('Ingresa el código de tu app', 400);

$pdo = ds_get_pdo();
$stmt = $pdo->prepare('SELECT totp_secret, totp_enabled FROM admins WHERE id = ?');
$stmt->execute([$adminId]);
$admin = $stmt->fetch();
if (!$admin || $admin['totp_secret'] === null) {
    ds_json_error('Primero genera el 2FA', 400);
}

if (!ds_totp_verify((string) $admin['totp_secret'], $code)) {
    ds_json_error('Código incorrecto. Revisa la hora de tu dispositivo e inténtalo de nuevo.', 400);
}

$upd = $pdo->prepare('UPDATE admins SET totp_enabled = 1 WHERE id = ?');
$upd->execute([$adminId]);

ds_json_success(['enabled' => true]);
