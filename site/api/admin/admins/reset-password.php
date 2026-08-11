<?php
declare(strict_types=1);

require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../lib/Response.php';
require __DIR__ . '/../../lib/AdminSession.php';
require __DIR__ . '/../../lib/Validate.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') ds_json_error('Método no permitido', 405);
$actorId = ds_require_rol(DS_ROL_DUENO);

$body = ds_read_json_body();
ds_admin_csrf_check($body['csrf_token'] ?? null);

$id   = (int) ($body['id'] ?? 0);
$pass = (string) ($body['password'] ?? '');

if ($id <= 0) ds_json_error('id es requerido', 400);
if (($errPass = ds_validate_password($pass)) !== null) ds_json_error($errPass, 400);

$pdo = ds_get_pdo();

$check = $pdo->prepare('SELECT id FROM admins WHERE id = ?');
$check->execute([$id]);
if (!$check->fetch()) ds_json_error('Cuenta no encontrada', 404);

$hash = password_hash($pass, PASSWORD_DEFAULT);
$stmt = $pdo->prepare('UPDATE admins SET password_hash = ?, password_changed_at = NOW() WHERE id = ?');
$stmt->execute([$hash, $id]);

// password_changed_at > admin_login_time cierra, en su siguiente petición, cualquier
// sesión que ese admin tuviera abierta (ds_admin_actual() en AdminSession.php). Si el
// dueño se está reseteando su PROPIA contraseña desde aquí, hay que re-sellar su propio
// login_time — si no, su siguiente petición lo expulsaría a él mismo.
if ($id === $actorId) {
    $_SESSION['admin_login_time'] = time();
}

ds_admin_log('admins/reset-password', "admin_id={$id}");

ds_json_success(['updated' => true]);
