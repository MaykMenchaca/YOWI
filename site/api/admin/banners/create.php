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

$titulo = ds_clean_string((string)($body['titulo'] ?? ''), 150) ?: null;
$imagen = ds_clean_url((string)($body['imagen'] ?? ''), 255) ?? '';
$enlace = ds_clean_url((string)($body['enlace'] ?? ''), 500);
$orden  = ds_to_positive_int($body['orden'] ?? 0);
$activo = isset($body['activo']) ? (int)(bool)$body['activo'] : 1;

if ($imagen === '') {
    ds_json_error('Falta la imagen de la promoción', 400);
}

$pdo  = ds_get_pdo();
$stmt = $pdo->prepare(
    'INSERT INTO banners (titulo, imagen, enlace, orden, activo) VALUES (?, ?, ?, ?, ?)'
);
$stmt->execute([$titulo, $imagen, $enlace, $orden, $activo]);

ds_json_success(['id' => (int) $pdo->lastInsertId()], 201);
