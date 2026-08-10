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

$nombre = ds_clean_string((string)($body['nombre'] ?? ''), 120);
$imagen = ds_clean_string((string)($body['imagen'] ?? ''), 255) ?: null;
$enlace = ds_clean_url((string)($body['enlace'] ?? ''), 500);
$orden  = ds_to_positive_int($body['orden'] ?? 0);
$activo = isset($body['activo']) ? (int)(bool)$body['activo'] : 1;

if ($nombre === '') {
    ds_json_error('El nombre de la marca es requerido', 400);
}

$slug = trim((string) preg_replace(
    '/[^a-z0-9]+/', '-',
    strtolower((string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nombre))
), '-');
if ($slug === '') $slug = 'marca';

$pdo   = ds_get_pdo();
$check = $pdo->prepare('SELECT id FROM brands WHERE slug = ?');
$check->execute([$slug]);
if ($check->fetch()) ds_json_error('Ya existe una marca con ese nombre', 409);

$stmt = $pdo->prepare(
    'INSERT INTO brands (nombre, slug, imagen, enlace, orden, activo) VALUES (?, ?, ?, ?, ?, ?)'
);
$stmt->execute([$nombre, $slug, $imagen, $enlace, $orden, $activo]);

ds_json_success(['id' => (int) $pdo->lastInsertId(), 'slug' => $slug], 201);
