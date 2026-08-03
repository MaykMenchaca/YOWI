<?php
declare(strict_types=1);

// API de galería de imágenes por producto (tabla product_images).
// GET  ?product_id=123                 -> lista las imágenes del producto (solo lectura).
// POST { action: "add" }               -> asocia una URL ya subida (vía upload-image.php) al producto.
// POST { action: "delete" }            -> elimina una imagen de la galería por id.
// POST { action: "reorder" }           -> reordena las imágenes de un producto según un array de ids.
// POST { action: "set_principal" }     -> copia la url de una imagen de la galería a products.imagen.
// Este endpoint nunca recibe archivos ($_FILES): solo URLs de texto ya generadas por
// upload-image.php o por el importador de CSV.

require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../lib/Response.php';
require __DIR__ . '/../../lib/AdminSession.php';
require __DIR__ . '/../../lib/Validate.php';

/** SELECT compartido por GET y por las acciones que devuelven la galería completa. */
function ds_product_images(PDO $pdo, int $productId): array
{
    $stmt = $pdo->prepare('SELECT id, url, orden FROM product_images WHERE product_id = ? ORDER BY orden');
    $stmt->execute([$productId]);
    return array_map(static fn($r) => [
        'id'    => (int) $r['id'],
        'url'   => $r['url'],
        'orden' => (int) $r['orden'],
    ], $stmt->fetchAll());
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    ds_require_admin();

    $productId = ds_to_positive_int($_GET['product_id'] ?? 0);
    if ($productId === 0) ds_json_error('product_id inválido', 400);

    $pdo = ds_get_pdo();
    ds_json_success(['data' => ds_product_images($pdo, $productId)]);
}

if ($method !== 'POST') ds_json_error('Método no permitido', 405);

ds_require_admin();

$body = ds_read_json_body();
ds_admin_csrf_check($body['csrf_token'] ?? null);

$action = (string) ($body['action'] ?? '');
$pdo    = ds_get_pdo();

if ($action === 'add') {
    $productId = ds_to_positive_int($body['product_id'] ?? 0);
    if ($productId === 0) ds_json_error('product_id inválido', 400);

    $prod = $pdo->prepare('SELECT id FROM products WHERE id = ?');
    $prod->execute([$productId]);
    if (!$prod->fetch()) ds_json_error('Producto no existe', 400);

    $url = ds_clean_url((string) ($body['url'] ?? ''), 255);
    if ($url === null) ds_json_error('URL de imagen inválida', 400);

    $ordenStmt = $pdo->prepare('SELECT COALESCE(MAX(orden), -1) + 1 FROM product_images WHERE product_id = ?');
    $ordenStmt->execute([$productId]);
    $orden = (int) $ordenStmt->fetchColumn();

    $ins = $pdo->prepare('INSERT INTO product_images (product_id, url, orden) VALUES (?, ?, ?)');
    $ins->execute([$productId, $url, $orden]);

    ds_json_success(['id' => (int) $pdo->lastInsertId(), 'url' => $url, 'orden' => $orden], 201);
}

if ($action === 'delete') {
    $id = ds_to_positive_int($body['id'] ?? 0);
    if ($id === 0) ds_json_error('id inválido', 400);

    $del = $pdo->prepare('DELETE FROM product_images WHERE id = ?');
    $del->execute([$id]);

    ds_json_success(['deleted' => $del->rowCount() > 0]);
}

if ($action === 'reorder') {
    $productId = ds_to_positive_int($body['product_id'] ?? 0);
    if ($productId === 0) ds_json_error('product_id inválido', 400);

    $ids = $body['ids'] ?? null;
    if (!is_array($ids) || count($ids) === 0) {
        ds_json_error('ids debe ser un arreglo no vacío', 400);
    }
    $cleanIds = [];
    foreach ($ids as $rawId) {
        $i = ds_to_positive_int($rawId);
        if ($i === 0) ds_json_error('ids debe contener solo enteros positivos', 400);
        $cleanIds[] = $i;
    }

    $pdo->beginTransaction();
    try {
        $upd = $pdo->prepare('UPDATE product_images SET orden = ? WHERE id = ? AND product_id = ?');
        foreach ($cleanIds as $orden => $id) {
            $upd->execute([$orden, $id, $productId]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log('images.php reorder: ' . $e->getMessage());
        ds_json_error('No se pudo reordenar la galería', 500);
    }

    ds_json_success(['data' => ds_product_images($pdo, $productId)]);
}

if ($action === 'set_principal') {
    $id = ds_to_positive_int($body['id'] ?? 0);
    if ($id === 0) ds_json_error('id inválido', 400);

    $img = $pdo->prepare('SELECT product_id, url FROM product_images WHERE id = ?');
    $img->execute([$id]);
    $row = $img->fetch();
    if (!$row) ds_json_error('Imagen no encontrada', 404);

    $upd = $pdo->prepare('UPDATE products SET imagen = ? WHERE id = ?');
    $upd->execute([$row['url'], (int) $row['product_id']]);

    ds_json_success(['ok' => true, 'imagen' => $row['url']]);
}

ds_json_error('Acción no reconocida', 400);
