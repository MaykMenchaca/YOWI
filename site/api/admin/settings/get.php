<?php
declare(strict_types=1);

require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../lib/Response.php';
require __DIR__ . '/../../lib/AdminSession.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') ds_json_error('Método no permitido', 405);
ds_require_admin();

$pdo  = ds_get_pdo();
$rows = $pdo->query('SELECT clave, valor FROM settings')->fetchAll();

$data = [];
foreach ($rows as $r) {
    $data[$r['clave']] = $r['valor'];
}

ds_json_success($data);
