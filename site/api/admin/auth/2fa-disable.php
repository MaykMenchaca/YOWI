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

// Para desactivar exige un código TOTP válido actual (evita que un atacante con la
// sesión abierta lo quite sin tener el segundo factor).
$code = preg_replace('/\D+/', '', (string) ($body['code'] ?? ''));

$pdo = ds_get_pdo();
$stmt = $pdo->prepare('SELECT totp_secret, totp_enabled FROM admins WHERE id = ?');
$stmt->execute([$adminId]);
$admin = $stmt->fetch();
if (!$admin || (int) $admin['totp_enabled'] !== 1) {
    ds_json_error('El 2FA no está activo', 400);
}

if ($code === '' || !ds_totp_verify((string) $admin['totp_secret'], $code)) {
    ds_json_error('Código de verificación inválido', 401);
}

$upd = $pdo->prepare('UPDATE admins SET totp_enabled = 0, totp_secret = NULL WHERE id = ?');
$upd->execute([$adminId]);

ds_json_success(['enabled' => false]);
