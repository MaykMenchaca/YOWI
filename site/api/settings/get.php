<?php
declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../lib/Response.php';
require __DIR__ . '/../lib/Settings.php';

// GET /api/settings/get.php — contenido editable (público), como objeto {clave: valor}.
//
// El filtro por lista blanca sigue siendo necesario aunque save.php ya restrinja la escritura:
// una fila insertada directo en la tabla (una migración, un script interno) se publicaría sola
// sin que nadie lo pidiera. La lista vive en lib/Settings.php — una sola fuente de verdad.
$permitidas = ds_settings_claves();

$pdo  = ds_get_pdo();
$rows = $pdo->query('SELECT clave, valor FROM settings')->fetchAll();

$data = [];
foreach ($rows as $r) {
    if (in_array($r['clave'], $permitidas, true)) {
        $data[$r['clave']] = $r['valor'];
    }
}

ds_json_success($data);
