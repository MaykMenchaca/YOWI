<?php
declare(strict_types=1);

require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../lib/Response.php';
require __DIR__ . '/../../lib/AdminSession.php';
require __DIR__ . '/../../lib/Totp.php';
require __DIR__ . '/../../lib/Recovery.php';

// Regenera los códigos de recuperación (invalida los anteriores). Exige un código TOTP
// válido actual para confirmar que es el dueño del segundo factor.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') ds_json_error('Método no permitido', 405);

$adminId = ds_require_admin();

$body = ds_read_json_body();
ds_admin_csrf_check($body['csrf_token'] ?? null);

$code = preg_replace('/\D+/', '', (string) ($body['code'] ?? ''));

$pdo = ds_get_pdo();
$stmt = $pdo->prepare('SELECT totp_secret, totp_enabled FROM admins WHERE id = ?');
$stmt->execute([$adminId]);
$admin = $stmt->fetch();
if (!$admin || (int) $admin['totp_enabled'] !== 1) {
    ds_json_error('Primero activa el 2FA', 400);
}
if ($code === '' || !ds_totp_verify((string) $admin['totp_secret'], $code)) {
    ds_json_error('Código de verificación inválido', 401);
}

$codes = ds_generate_recovery_codes($pdo, $adminId, 10);

ds_json_success(['recovery_codes' => $codes]);
