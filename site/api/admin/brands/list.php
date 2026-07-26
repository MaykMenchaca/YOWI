<?php
declare(strict_types=1);

require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../lib/Response.php';
require __DIR__ . '/../../lib/AdminSession.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') ds_json_error('Método no permitido', 405);
ds_require_admin();

$pdo  = ds_get_pdo();
$rows = $pdo->query(
    'SELECT id, nombre, slug, imagen, enlace, orden, activo
     FROM brands
     ORDER BY orden ASC, nombre ASC'
)->fetchAll();

$data = array_map(static fn($r) => [
    'id'     => (int) $r['id'],
    'nombre' => $r['nombre'],
    'slug'   => $r['slug'],
    'imagen' => $r['imagen'],
    'enlace' => $r['enlace'],
    'orden'  => (int) $r['orden'],
    'activo' => (bool) $r['activo'],
], $rows);

ds_json_success($data);
