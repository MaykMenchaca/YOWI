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
               p.cantidad, p.unidad, p.descripcion, p.precio, p.precio_original,
               p.imagen, p.badge, p.sku, p.destacado
        FROM products p
        JOIN categories c ON c.id = p.category_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY p.destacado DESC, p.id DESC';

$pdo  = ds_get_pdo();
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Sabores (F3.5) y galería (F4.5): una sola consulta con IN por tabla para todos los
// productos de esta página, en vez de una consulta por producto. Se agrupan por
// product_id abajo.
$productIds = array_map(static fn($r) => (int) $r['id'], $rows);
$flavorsByProduct = [];
$imagesByProduct = [];
if (!empty($productIds)) {
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));

    $iStmt = $pdo->prepare(
        "SELECT product_id, url FROM product_images WHERE product_id IN ($placeholders) ORDER BY orden"
    );
    $iStmt->execute($productIds);
    foreach ($iStmt->fetchAll() as $img) {
        $imagesByProduct[(int) $img['product_id']][] = $img['url'];
    }

    $fStmt = $pdo->prepare(
        "SELECT id, product_id, nombre, slug
         FROM product_flavors
         WHERE product_id IN ($placeholders) AND activo = 1
         ORDER BY orden"
    );
    $fStmt->execute($productIds);
    foreach ($fStmt->fetchAll() as $f) {
        // Un sabor es solo un nombre: el precio siempre es el del producto (F sin
        // precio/stock propios), no hay nada más que devolver aquí.
        $flavorsByProduct[(int) $f['product_id']][] = [
            'id'     => (int) $f['id'],
            'nombre' => $f['nombre'],
            'slug'   => $f['slug'],
        ];
    }
}

$data = array_map(static function ($r) use ($flavorsByProduct, $imagesByProduct) {
    return [
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
        'imagen'         => $r['imagen'],
        'badge'          => $r['badge'],
        'sku'            => $r['sku'],
        'destacado'      => (bool) $r['destacado'],
        'sabores'        => $flavorsByProduct[(int) $r['id']] ?? [],
        'imagenes'       => $imagesByProduct[(int) $r['id']] ?? [],
    ];
}, $rows);

ds_json_success($data);
