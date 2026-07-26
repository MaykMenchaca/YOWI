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
$titulo = ds_clean_string((string)($body['titulo'] ?? ''), 150) ?: null;
$imagen = ds_clean_string((string)($body['imagen'] ?? ''), 255);
$enlace = ds_clean_url((string)($body['enlace'] ?? ''), 500);
$orden  = ds_to_positive_int($body['orden'] ?? 0);
$activo = isset($body['activo']) ? (int)(bool)$body['activo'] : 1;

if ($id === 0) {
    ds_json_error('ID requerido', 400);
}

$pdo = ds_get_pdo();

// Si no llega imagen nueva, conservar la actual.
if ($imagen !== '') {
    $imagenFinal = $imagen;
} else {
    $imgStmt = $pdo->prepare('SELECT imagen FROM banners WHERE id = ?');
    $imgStmt->execute([$id]);
    $imagenFinal = (string) $imgStmt->fetchColumn();
}
if ($imagenFinal === '') {
    ds_json_error('Falta la imagen de la promoción', 400);
}

$stmt = $pdo->prepare(
    'UPDATE banners SET titulo=?, imagen=?, enlace=?, orden=?, activo=? WHERE id=?'
);
$stmt->execute([$titulo, $imagenFinal, $enlace, $orden, $activo, $id]);

ds_json_success(['updated' => $stmt->rowCount() > 0]);
