<?php
declare(strict_types=1);

require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../lib/Response.php';
require __DIR__ . '/../../lib/AdminSession.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') ds_json_error('Método no permitido', 405);
ds_require_admin();

$body = ds_read_json_body();
ds_admin_csrf_check($body['csrf_token'] ?? null);

// Confirmación explícita: el cliente debe enviar confirm === "BORRAR".
if (($body['confirm'] ?? '') !== 'BORRAR') {
    ds_json_error('Confirmación inválida', 400);
}

// Borra TODAS las marcas. Sin FK: products.marca es texto libre, no rompe productos.
$pdo      = ds_get_pdo();
$borrados = (int) $pdo->exec('DELETE FROM brands');

ds_json_success(['borrados' => $borrados]);
