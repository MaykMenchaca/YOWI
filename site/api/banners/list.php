<?php
declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../lib/Response.php';

// GET /api/banners/list.php — banners activos del carrusel del hero (público).

$pdo  = ds_get_pdo();
$rows = $pdo->query(
    'SELECT id, titulo, imagen, enlace
     FROM banners
     WHERE activo = 1
     ORDER BY orden ASC, id DESC'
)->fetchAll();

$data = array_map(static fn($r) => [
    'id'     => (int) $r['id'],
    'titulo' => $r['titulo'],
    'imagen' => $r['imagen'],
    'enlace' => $r['enlace'],
], $rows);

ds_json_success($data);
