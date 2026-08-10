<?php
declare(strict_types=1);

require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../lib/Response.php';
require __DIR__ . '/../../lib/AdminSession.php';
require __DIR__ . '/../../lib/Validate.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') ds_json_error('Método no permitido', 405);
ds_require_rol(DS_ROL_OPERADOR);

$body = ds_read_json_body();
ds_admin_csrf_check($body['csrf_token'] ?? null);

$nombre      = ds_clean_string((string)($body['nombre'] ?? ''), 255);
$marca       = ds_clean_string((string)($body['marca'] ?? ''), 120);
$cat_id      = ds_to_positive_int($body['category_id'] ?? 0);
$cantidad    = ds_clean_string((string)($body['cantidad'] ?? ''), 80);
$unidad      = ds_clean_string((string)($body['unidad'] ?? ''), 20) ?: null;
$descripcion = ds_clean_string((string)($body['descripcion'] ?? ''), 5000);
$precio      = ds_to_positive_float($body['precio'] ?? 0);
$precio_orig = isset($body['precio_original']) && $body['precio_original'] !== '' && $body['precio_original'] !== null
    ? ds_to_positive_float($body['precio_original'])
    : null;
// NULL = sin control de inventario; número (>=0) = inventario real, 0 = agotado.
$stock       = (isset($body['stock']) && $body['stock'] !== '' && $body['stock'] !== null)
    ? max(0, (int) $body['stock'])
    : null;
// Solo rutas relativas o http/https; bloquea javascript:/data:. Fallback al placeholder.
$imagen      = ds_clean_url((string)($body['imagen'] ?? ''), 255) ?? 'assets/img/producto-placeholder.svg';
$badge       = ds_clean_string((string)($body['badge'] ?? ''), 30) ?: null;
$sku         = ds_clean_string((string)($body['sku'] ?? ''), 64) ?: null;
$destacado   = !empty($body['destacado']) ? 1 : 0;
$activo      = isset($body['activo']) ? (int)(bool)$body['activo'] : 1;

if ($nombre === '' || $marca === '' || $cat_id === 0 || $precio <= 0) {
    ds_json_error('Campos requeridos: nombre, marca, category_id, precio', 400);
}
if ($precio_orig !== null && $precio_orig <= $precio) {
    ds_json_error('El precio original (tachado) debe ser mayor que el precio actual', 400);
}

$pdo = ds_get_pdo();
$cat = $pdo->prepare('SELECT id FROM categories WHERE id = ?');
$cat->execute([$cat_id]);
if (!$cat->fetch()) ds_json_error('Categoría no existe', 400);

$stmt = $pdo->prepare(
    'INSERT INTO products (nombre, marca, category_id, cantidad, unidad, descripcion, precio, precio_original, stock, imagen, badge, sku, destacado, activo)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
try {
    $stmt->execute([$nombre, $marca, $cat_id, $cantidad, $unidad, $descripcion ?: null, $precio, $precio_orig, $stock, $imagen, $badge, $sku, $destacado, $activo]);
} catch (PDOException $e) {
    if ((int) $e->getCode() === 23000 || ($e->errorInfo[1] ?? null) === 1062) {
        ds_json_error('Ya existe un producto con ese SKU', 409);
    }
    throw $e;
}

ds_json_success(['id' => (int) $pdo->lastInsertId()], 201);
