<?php
declare(strict_types=1);

require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../lib/Response.php';
require __DIR__ . '/../../lib/AdminSession.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') ds_json_error('Método no permitido', 405);

ds_require_admin();

$page  = max(1, (int)($_GET['page']  ?? 1));
// El panel pide explícitamente limit=9999 para traer el catálogo completo y paginar/
// filtrar en cliente (products.js). Con el techo anterior (100) el catálogo real
// (335 productos) quedaba truncado: búsqueda, filtro por categoría y "Editar" en
// productos fuera de los primeros 100 fallaban en silencio.
$limit = min(5000, max(1, (int)($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;
$q      = isset($_GET['q'])   ? trim((string)$_GET['q'])   : '';
$cat    = isset($_GET['cat']) ? (int)$_GET['cat']          : 0;

$where  = [];
$params = [];

if ($q !== '') {
    $where[] = '(p.nombre LIKE ? OR p.marca LIKE ? OR p.sku LIKE ?)';
    $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%";
}
if ($cat > 0) {
    $where[] = 'p.category_id = ?';
    $params[] = $cat;
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$pdo = ds_get_pdo();

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM products p $whereClause");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT p.id, p.nombre, p.marca, c.nombre AS categoria, c.id AS category_id,
            p.cantidad, p.unidad, p.precio, p.precio_original, p.stock, p.imagen,
            p.badge, p.sku, p.destacado, p.activo, p.created_at
     FROM products p
     JOIN categories c ON c.id = p.category_id
     $whereClause
     ORDER BY p.id DESC
     LIMIT $limit OFFSET $offset"
);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$data = array_map(static fn($r) => [
    'id'             => (int) $r['id'],
    'nombre'         => $r['nombre'],
    'marca'          => $r['marca'],
    'categoria'      => $r['categoria'],
    'category_id'    => (int) $r['category_id'],
    'cantidad'       => $r['cantidad'],
    'unidad'         => $r['unidad'],
    'precio'         => (float) $r['precio'],
    'precio_original'=> $r['precio_original'] !== null ? (float) $r['precio_original'] : null,
    'stock'          => $r['stock'] !== null ? (int) $r['stock'] : null,
    'imagen'         => $r['imagen'],
    'badge'          => $r['badge'],
    'sku'            => $r['sku'],
    'destacado'      => (bool) $r['destacado'],
    'activo'         => (bool) $r['activo'],
    'created_at'     => $r['created_at'],
], $rows);

ds_json_success(['data' => $data, 'total' => $total, 'page' => $page, 'limit' => $limit]);
