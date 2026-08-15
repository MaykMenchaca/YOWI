<?php
declare(strict_types=1);

require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../lib/Response.php';
require __DIR__ . '/../../lib/AdminSession.php';

// Lista de clientes — vista cargada de datos personales (nombre, correo, teléfono), así
// que solo el dueño puede verla: mismo criterio que los respaldos (backup/export.php).
// Antes no existía ningún endpoint del panel que tocara la tabla de clientes.

if ($_SERVER['REQUEST_METHOD'] !== 'GET') ds_json_error('Método no permitido', 405);
ds_require_rol(DS_ROL_DUENO);

$pdo  = ds_get_pdo();
$stmt = $pdo->query(
    'SELECT u.id, u.nombre, u.email, u.telefono, u.created_at,
            COUNT(o.id) AS pedidos_count
     FROM users u
     LEFT JOIN orders o ON o.user_id = u.id
     GROUP BY u.id
     ORDER BY u.created_at DESC'
);

$rows = $stmt->fetchAll();
$data = array_map(static fn($r) => [
    'id'             => (int) $r['id'],
    'nombre'         => $r['nombre'],
    'email'          => $r['email'],
    'telefono'       => $r['telefono'],
    'created_at'     => $r['created_at'],
    'pedidos_count'  => (int) $r['pedidos_count'],
], $rows);

ds_json_success($data);
