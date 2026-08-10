<?php
declare(strict_types=1);

// Sabores por producto: nombre + stock propio + precio propio (panel admin, F3.3).
// GET  -> lista los sabores actuales de un producto.
// POST -> reemplaza el conjunto completo de sabores del producto (sincronización por
//         slug vía ds_sync_product_flavors, compartida con el importador de CSV).

require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../lib/Response.php';
require __DIR__ . '/../../lib/AdminSession.php';
require __DIR__ . '/../../lib/Validate.php';
require __DIR__ . '/../../lib/Flavors.php';

/**
 * SELECT + mapeo compartido entre el GET y la respuesta final del POST, para que el
 * panel siempre reciba el mismo formato de datos (con ids reales tras sincronizar).
 */
function ds_flavors_list(PDO $pdo, int $productId): array
{
    $stmt = $pdo->prepare(
        'SELECT id, nombre, slug, stock, precio, orden, activo
         FROM product_flavors
         WHERE product_id = ?
         ORDER BY orden'
    );
    $stmt->execute([$productId]);
    $rows = $stmt->fetchAll();

    return array_map(static fn($r) => [
        'id'     => (int) $r['id'],
        'nombre' => $r['nombre'],
        'slug'   => $r['slug'],
        'stock'  => $r['stock'] !== null ? (int) $r['stock'] : null,
        'precio' => $r['precio'] !== null ? (float) $r['precio'] : null,
        'orden'  => (int) $r['orden'],
        'activo' => (bool) $r['activo'],
    ], $rows);
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    ds_require_rol(DS_ROL_LECTURA);

    $productId = ds_to_positive_int($_GET['product_id'] ?? 0);
    if ($productId === 0) ds_json_error('product_id requerido', 400);

    $pdo = ds_get_pdo();
    ds_json_success(ds_flavors_list($pdo, $productId));
}

if ($method === 'POST') {
    ds_require_rol(DS_ROL_OPERADOR);

    $body = ds_read_json_body();
    ds_admin_csrf_check($body['csrf_token'] ?? null);

    $productId = ds_to_positive_int($body['product_id'] ?? 0);
    $pdo = ds_get_pdo();

    if ($productId === 0) ds_json_error('Producto no existe', 400);
    $prod = $pdo->prepare('SELECT id FROM products WHERE id = ?');
    $prod->execute([$productId]);
    if (!$prod->fetch()) ds_json_error('Producto no existe', 400);

    $saboresRaw = isset($body['sabores']) && is_array($body['sabores']) ? $body['sabores'] : [];

    $parsedFlavors = [];
    foreach ($saboresRaw as $v) {
        $nombre = ds_clean_string((string) ($v['nombre'] ?? ''), 80);
        if ($nombre === '') {
            continue; // entrada vacía tras limpiar: se descarta silenciosamente.
        }
        $stock = (isset($v['stock']) && $v['stock'] !== null && $v['stock'] !== '')
            ? max(0, (int) $v['stock'])
            : null;
        $precio = (isset($v['precio']) && $v['precio'] !== null && $v['precio'] !== '')
            ? max(0.0, (float) $v['precio'])
            : null;
        $parsedFlavors[] = ['nombre' => $nombre, 'stock' => $stock, 'precio' => $precio];
    }

    try {
        $pdo->beginTransaction();
        ds_sync_product_flavors($pdo, $productId, $parsedFlavors);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('products/flavors.php: ' . $e->getMessage());
        ds_json_error('No se pudo guardar', 500);
    }

    ds_admin_log('sabores_actualizados', 'product_id=' . $productId);

    ds_json_success(ds_flavors_list($pdo, $productId));
}

ds_json_error('Método no permitido', 405);
