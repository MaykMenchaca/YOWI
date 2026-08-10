<?php
declare(strict_types=1);

require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../lib/Response.php';
require __DIR__ . '/../../lib/AdminSession.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') ds_json_error('Método no permitido', 405);
ds_require_rol(DS_ROL_DUENO);

$body = ds_read_json_body();
ds_admin_csrf_check($body['csrf_token'] ?? null);

if (($body['confirm'] ?? '') !== 'BORRAR') {
    ds_json_error('Confirmación inválida', 400);
}

$pdo = ds_get_pdo();

// products.category_id es ON DELETE RESTRICT: no se puede borrar una categoría con
// productos. Por eso se eliminan solo las categorías SIN productos (limpia las vacías).
$enUso = (int) $pdo->query('SELECT COUNT(*) FROM categories WHERE id IN (SELECT DISTINCT category_id FROM products)')->fetchColumn();
$borradas = (int) $pdo->exec('DELETE FROM categories WHERE id NOT IN (SELECT DISTINCT category_id FROM products)');

ds_json_success(['borradas' => $borradas, 'en_uso' => $enUso]);
