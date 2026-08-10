<?php
declare(strict_types=1);

require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../lib/Response.php';
require __DIR__ . '/../../lib/AdminSession.php';
require __DIR__ . '/../../lib/RateLimit.php';

// Cambio de la PROPIA contraseña (cualquier rol). Antes de esto no existía ninguna forma
// de cambiar una contraseña de admin por HTTP — solo scripts/create-admin.php por CLI. Sin
// este endpoint, la contraseña inicial que el dueño le da a un empleado sería la única
// que ese empleado tendría para siempre.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') ds_json_error('Método no permitido', 405);
$adminId = ds_require_admin();

$body = ds_read_json_body();
ds_admin_csrf_check($body['csrf_token'] ?? null);

ds_rate_limit_ip('pwchange', ds_client_ip(), 10, 15);

$actual = (string) ($body['password_actual'] ?? '');
$nueva  = (string) ($body['password_nueva'] ?? '');

if ($actual === '') ds_json_error('Ingresa tu contraseña actual', 400);
if (strlen($nueva) < 8) ds_json_error('La nueva contraseña debe tener al menos 8 caracteres', 400);

$pdo  = ds_get_pdo();
$stmt = $pdo->prepare('SELECT password_hash FROM admins WHERE id = ?');
$stmt->execute([$adminId]);
$admin = $stmt->fetch();
if (!$admin) ds_json_error('Cuenta no encontrada', 404);

if (!password_verify($actual, (string) $admin['password_hash'])) {
    ds_json_error('Tu contraseña actual no es correcta', 401);
}

$hash = password_hash($nueva, PASSWORD_DEFAULT);
$upd  = $pdo->prepare('UPDATE admins SET password_hash = ?, password_changed_at = NOW() WHERE id = ?');
$upd->execute([$hash, $adminId]);

// Re-sellar el login_time: sin esto, la comparación password_changed_at > admin_login_time
// (ds_admin_actual() en AdminSession.php) expulsaría al propio admin en su siguiente
// petición, justo después de haber cambiado su contraseña con éxito.
$_SESSION['admin_login_time'] = time();

ds_json_success(['updated' => true]);
