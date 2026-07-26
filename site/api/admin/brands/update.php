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

$id     = ds_to_positive_int($body['id'] ?? 0);
$nombre = ds_clean_string((string)($body['nombre'] ?? ''), 120);
$imagen = ds_clean_string((string)($body['imagen'] ?? ''), 255);
$enlace = ds_clean_url((string)($body['enlace'] ?? ''), 500);
$orden  = ds_to_positive_int($body['orden'] ?? 0);
$activo = isset($body['activo']) ? (int)(bool)$body['activo'] : 1;

if ($id === 0)      ds_json_error('ID requerido', 400);
if ($nombre === '') ds_json_error('El nombre de la marca es requerido', 400);

$slug = trim((string) preg_replace(
    '/[^a-z0-9]+/', '-',
    strtolower((string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nombre))
), '-');
if ($slug === '') $slug = 'marca';

$pdo = ds_get_pdo();

// El slug es único: si choca con OTRA marca, avisar.
$check = $pdo->prepare('SELECT id FROM brands WHERE slug = ? AND id <> ?');
$check->execute([$slug, $id]);
if ($check->fetch()) ds_json_error('Ya existe otra marca con ese nombre', 409);

// Si no llega logo nuevo, conservar el actual.
if ($imagen !== '') {
    $imagenFinal = $imagen;
} else {
    $imgStmt = $pdo->prepare('SELECT imagen FROM brands WHERE id = ?');
    $imgStmt->execute([$id]);
    $imagenFinal = $imgStmt->fetchColumn();
    $imagenFinal = $imagenFinal !== false ? $imagenFinal : null;
}

$stmt = $pdo->prepare(
    'UPDATE brands SET nombre=?, slug=?, imagen=?, enlace=?, orden=?, activo=? WHERE id=?'
);
$stmt->execute([$nombre, $slug, $imagenFinal, $enlace, $orden, $activo, $id]);

ds_json_success(['updated' => $stmt->rowCount() > 0]);
