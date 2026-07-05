<?php
declare(strict_types=1);

require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../lib/Response.php';
require __DIR__ . '/../../lib/AdminSession.php';
require __DIR__ . '/../../lib/Validate.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') ds_json_error('Método no permitido', 405);
ds_require_admin();

$body = ds_read_json_body();
ds_admin_csrf_check($body['csrf_token'] ?? null);

$id          = ds_to_positive_int($body['id'] ?? 0);
$nombre      = ds_clean_string((string)($body['nombre'] ?? ''), 255);
$marca       = ds_clean_string((string)($body['marca'] ?? ''), 120);
$cat_id      = ds_to_positive_int($body['category_id'] ?? 0);
$cantidad    = ds_clean_string((string)($body['cantidad'] ?? ''), 80);
$descripcion = ds_clean_string((string)($body['descripcion'] ?? ''), 5000);
$precio      = ds_to_positive_float($body['precio'] ?? 0);
$precio_orig = isset($body['precio_original']) && $body['precio_original'] !== '' && $body['precio_original'] !== null
    ? ds_to_positive_float($body['precio_original'])
    : null;
$stock       = ds_to_positive_int($body['stock'] ?? 0);
$imagen      = ds_clean_string((string)($body['imagen'] ?? ''), 255);
$badge       = ds_clean_string((string)($body['badge'] ?? ''), 30) ?: null;
$destacado   = !empty($body['destacado']) ? 1 : 0;
$activo      = isset($body['activo']) ? (int)(bool)$body['activo'] : 1;

if ($id === 0 || $nombre === '' || $marca === '' || $cat_id === 0 || $precio <= 0) {
    ds_json_error('Campos requeridos: id, nombre, marca, category_id, precio', 400);
}
if ($precio_orig !== null && $precio_orig <= $precio) {
    ds_json_error('El precio original (tachado) debe ser mayor que el precio actual', 400);
}

$pdo = ds_get_pdo();
$cat = $pdo->prepare('SELECT id FROM categories WHERE id = ?');
$cat->execute([$cat_id]);
if (!$cat->fetch()) ds_json_error('Categoría no existe', 400);

if ($imagen !== '') {
    $imagenFinal = $imagen;
} else {
    $imgStmt = $pdo->prepare('SELECT imagen FROM products WHERE id = ?');
    $imgStmt->execute([$id]);
    $imagenFinal = (string) $imgStmt->fetchColumn();
}

$stmt = $pdo->prepare(
    'UPDATE products SET nombre=?, marca=?, category_id=?, cantidad=?, descripcion=?,
     precio=?, precio_original=?, stock=?, imagen=?, badge=?, destacado=?, activo=?
     WHERE id=?'
);
$stmt->execute([$nombre, $marca, $cat_id, $cantidad, $descripcion ?: null, $precio, $precio_orig, $stock, $imagenFinal, $badge, $destacado, $activo, $id]);

ds_json_success(['updated' => $stmt->rowCount() > 0]);
