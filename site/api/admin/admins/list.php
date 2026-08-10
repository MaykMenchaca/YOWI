<?php
declare(strict_types=1);

require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../lib/Response.php';
require __DIR__ . '/../../lib/AdminSession.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') ds_json_error('Método no permitido', 405);
ds_require_rol(DS_ROL_DUENO);

$pdo  = ds_get_pdo();
$stmt = $pdo->query(
    'SELECT id, nombre, email, rol, activo, totp_enabled, created_at
     FROM admins
     ORDER BY activo DESC, nombre ASC'
);

$rows = $stmt->fetchAll();
$data = array_map(static fn($r) => [
    'id'           => (int) $r['id'],
    'nombre'       => $r['nombre'],
    'email'        => $r['email'],
    'rol'          => $r['rol'],
    'activo'       => (int) $r['activo'] === 1,
    'totp_enabled' => (int) $r['totp_enabled'] === 1,
    'created_at'   => $r['created_at'],
], $rows);
// Nunca password_hash ni totp_secret aquí.

// Log explícito: esto es GET, así que el registro automático de ds_admin_csrf_check
// (solo corre en POST) nunca lo vería. Mismo patrón que backup/export.php.
ds_admin_log('admins/list', null);

ds_json_success($data);
