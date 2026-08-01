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

$pdo = ds_get_pdo();
$stmt = $pdo->prepare('SELECT email, totp_enabled FROM admins WHERE id = ?');
$stmt->execute([$adminId]);
$admin = $stmt->fetch();
if (!$admin) ds_json_error('Administrador no encontrado', 404);

if ((int) $admin['totp_enabled'] === 1) {
    ds_json_error('El 2FA ya está activo. Desactívalo antes de generar uno nuevo.', 409);
}

// Genera un secreto nuevo y lo guarda como PENDIENTE (totp_enabled sigue en 0 hasta
// que el admin confirme con un código válido en 2fa-activate.php).
$secret = ds_totp_secret();
$upd = $pdo->prepare('UPDATE admins SET totp_secret = ?, totp_enabled = 0 WHERE id = ?');
$upd->execute([$secret, $adminId]);

$uri = ds_totp_uri($secret, (string) $admin['email'], 'DS Suplementos');

ds_json_success([
    'secret' => $secret,       // para entrada manual en la app autenticadora
    'otpauth_uri' => $uri,     // para generar el código QR
]);
