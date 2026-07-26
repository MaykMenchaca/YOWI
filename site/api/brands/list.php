<?php
declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../lib/Response.php';

// GET /api/brands/list.php — marcas activas (público) con el conteo de productos.

$pdo  = ds_get_pdo();
$rows = $pdo->query(
    'SELECT b.id, b.nombre, b.imagen, b.enlace,
            (SELECT COUNT(*) FROM products p WHERE p.marca = b.nombre AND p.activo = 1) AS productos
     FROM brands b
     WHERE b.activo = 1
     ORDER BY b.orden ASC, b.nombre ASC'
)->fetchAll();

$data = array_map(static fn($r) => [
    'id'        => (int) $r['id'],
    'nombre'    => $r['nombre'],
    'imagen'    => $r['imagen'],
    'enlace'    => $r['enlace'],
    'productos' => (int) $r['productos'],
], $rows);

ds_json_success($data);
