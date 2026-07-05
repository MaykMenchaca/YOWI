<?php
declare(strict_types=1);

require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../lib/Response.php';
require __DIR__ . '/../../lib/AdminSession.php';

// GET /api/admin/auth/me.php — devuelve el admin actual + csrf_token.
// Si no hay sesión activa devuelve admin:null (no es error).
ds_admin_session_start();

$adminId = ds_current_admin_id();

if ($adminId === null) {
    ds_json_success(['admin' => null, 'csrf_token' => ds_admin_csrf_token()]);
}

$pdo  = ds_get_pdo();
$stmt = $pdo->prepare('SELECT id, nombre, email FROM admins WHERE id = ?');
$stmt->execute([$adminId]);
$admin = $stmt->fetch();

if (!$admin) {
    unset($_SESSION['admin_id']);
    ds_json_success(['admin' => null, 'csrf_token' => ds_admin_csrf_token()]);
}

ds_json_success([
    'admin'      => ['id' => (int) $admin['id'], 'nombre' => $admin['nombre'], 'email' => $admin['email']],
    'csrf_token' => ds_admin_csrf_token(),
]);
