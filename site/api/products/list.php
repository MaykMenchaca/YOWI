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
               p.imagen, p.badge, p.sku, p.destacado
        FROM products p
        JOIN categories c ON c.id = p.category_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY p.destacado DESC, p.id DESC';

$pdo  = ds_get_pdo();
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Sabores (F3.5): una sola consulta con IN para todos los productos de esta página, en
// vez de una consulta por producto. Se agrupan por product_id abajo.
$productIds = array_map(static fn($r) => (int) $r['id'], $rows);
$flavorsByProduct = [];
if (!empty($productIds)) {
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    $fStmt = $pdo->prepare(
        "SELECT id, product_id, nombre, slug, stock, precio
         FROM product_flavors
         WHERE product_id IN ($placeholders) AND activo = 1
         ORDER BY orden"
    );
    $fStmt->execute($productIds);
    foreach ($fStmt->fetchAll() as $f) {
        $flavorsByProduct[(int) $f['product_id']][] = [
            'id'     => (int) $f['id'],
            'nombre' => $f['nombre'],
            'slug'   => $f['slug'],
            'stock'  => $f['stock'] !== null ? (int) $f['stock'] : null,
            'precio' => $f['precio'] !== null ? (float) $f['precio'] : null,
        ];
    }
}

$data = array_map(static function ($r) use ($flavorsByProduct) {
    $precioBase = (float) $r['precio'];
    $sabores    = $flavorsByProduct[(int) $r['id']] ?? [];

    // "Desde $X" (F3.5): solo si el producto tiene sabores y sus precios EFECTIVOS
    // (el propio del sabor, o si no tiene, el del producto) no son todos iguales.
    $precioDesde = null;
    if (!empty($sabores)) {
        $efectivos = array_map(static fn($s) => $s['precio'] ?? $precioBase, $sabores);
        $min = min($efectivos);
        $max = max($efectivos);
        if (abs($max - $min) > 0.001) {
            $precioDesde = $min;
        }
    }

    return [
        'id'             => (int) $r['id'],
        'nombre'         => $r['nombre'],
        'marca'          => $r['marca'],
        'categoria'      => $r['categoria'],
        'categoria_slug' => $r['categoria_slug'],
        'cantidad'       => $r['cantidad'],
        'unidad'         => $r['unidad'],
        'descripcion'    => $r['descripcion'] ?? '',
        'precio'         => $precioBase,
        'precio_original'=> $r['precio_original'] !== null ? (float) $r['precio_original'] : null,
        'precio_desde'   => $precioDesde,
        'stock'          => $r['stock'] !== null ? (int) $r['stock'] : null,
        'imagen'         => $r['imagen'],
        'badge'          => $r['badge'],
        'sku'            => $r['sku'],
        'destacado'      => (bool) $r['destacado'],
        'sabores'        => $sabores,
    ];
}, $rows);

ds_json_success($data);
