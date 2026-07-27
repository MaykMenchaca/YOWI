<?php
declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../lib/Response.php';

// GET /api/settings/get.php — contenido editable (público), como objeto {clave: valor}.
$pdo  = ds_get_pdo();
$rows = $pdo->query('SELECT clave, valor FROM settings')->fetchAll();

$data = [];
foreach ($rows as $r) {
    $data[$r['clave']] = $r['valor'];
}

ds_json_success($data);
