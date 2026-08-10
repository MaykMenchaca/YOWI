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

$id     = (int)($body['id'] ?? 0);
$nombre = ds_clean_string((string)($body['nombre'] ?? ''), 100);
$orden  = (int)($body['orden'] ?? 0);

if ($id <= 0 || $nombre === '') ds_json_error('id y nombre son requeridos', 400);

$slug = trim((string) preg_replace(
    '/[^a-z0-9]+/', '-',
    strtolower((string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nombre))
), '-');

$pdo   = ds_get_pdo();
$check = $pdo->prepare('SELECT id FROM categories WHERE slug = ? AND id != ?');
$check->execute([$slug, $id]);
if ($check->fetch()) ds_json_error('Ya existe otra categoría con ese nombre', 409);

$stmt = $pdo->prepare('UPDATE categories SET nombre=?, slug=?, orden=? WHERE id=?');
$stmt->execute([$nombre, $slug, $orden, $id]);

ds_json_success(['updated' => $stmt->rowCount() > 0, 'slug' => $slug]);
