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

// Borrar TODO el catálogo. Seguro: order_items.producto_id es ON DELETE SET NULL,
// así que los pedidos conservan su historial (nombre_producto) sin romperse.
$pdo      = ds_get_pdo();
$borrados = (int) $pdo->exec('DELETE FROM products');

ds_json_success(['borrados' => $borrados]);
