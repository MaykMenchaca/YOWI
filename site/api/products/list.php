<?php
declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../lib/Response.php';

// GET /api/products/list.php — catálogo público, reemplaza al JSON demo.
// Parámetros opcionales: ?cat=slug&q=texto&destacados=1

$destacados = !empty($_GET['destacados']);
$catSlug    = isset($_GET['cat']) ? trim((string)$_GET['cat']) : '';
$q          = isset($_GET['q'])   ? trim((string)$_GET['q'])   : '';

$where  = ['p.activo = 1'];
$params = [];

if ($destacados) {
    $where[] = 'p.destacado = 1';
}
if ($catSlug !== '') {
    $where[] = 'c.slug = ?';
    $params[] = $catSlug;
}
if ($q !== '') {
    $where[] = '(p.nombre LIKE ? OR p.marca LIKE ?)';
    $params[] = "%$q%";
    $params[] = "%$q%";
}

$sql = 'SELECT p.id, p.nombre, p.marca, c.nombre AS categoria, c.slug AS categoria_slug,
               p.cantidad, p.unidad, p.descripcion, p.precio, p.precio_original, p.stock,
               p.imagen, p.badge, p.destacado
        FROM products p
        JOIN categories c ON c.id = p.category_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY p.destacado DESC, p.id DESC';

$pdo  = ds_get_pdo();
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$data = array_map(static fn($r) => [
    'id'             => (int) $r['id'],
    'nombre'         => $r['nombre'],
    'marca'          => $r['marca'],
    'categoria'      => $r['categoria'],
    'categoria_slug' => $r['categoria_slug'],
    'cantidad'       => $r['cantidad'],
    'unidad'         => $r['unidad'],
    'descripcion'    => $r['descripcion'] ?? '',
    'precio'         => (float) $r['precio'],
    'precio_original'=> $r['precio_original'] !== null ? (float) $r['precio_original'] : null,
    'stock'          => $r['stock'] !== null ? (int) $r['stock'] : null,
    'imagen'         => $r['imagen'],
    'badge'          => $r['badge'],
    'destacado'      => (bool) $r['destacado'],
], $rows);

ds_json_success($data);
