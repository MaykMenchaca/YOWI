<?php
declare(strict_types=1);

require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../lib/Response.php';
require __DIR__ . '/../../lib/AdminSession.php';

ds_require_admin();

$pdo  = ds_get_pdo();
$stmt = $pdo->query(
    'SELECT c.id, c.nombre, c.slug, c.orden,
            COUNT(p.id) AS productos_count
     FROM categories c
     LEFT JOIN products p ON p.category_id = c.id AND p.activo = 1
     GROUP BY c.id
     ORDER BY c.orden ASC, c.nombre ASC'
);

$rows = $stmt->fetchAll();
$data = array_map(static fn($r) => [
    'id'              => (int) $r['id'],
    'nombre'          => $r['nombre'],
    'slug'            => $r['slug'],
    'orden'           => (int) $r['orden'],
    'productos_count' => (int) $r['productos_count'],
], $rows);

ds_json_success($data);
