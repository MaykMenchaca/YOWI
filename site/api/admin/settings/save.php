<?php
declare(strict_types=1);

require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../lib/Response.php';
require __DIR__ . '/../../lib/AdminSession.php';
require __DIR__ . '/../../lib/Validate.php';
require __DIR__ . '/../../lib/Settings.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') ds_json_error('Método no permitido', 405);
ds_require_rol(DS_ROL_DUENO);

$body = ds_read_json_body();
ds_admin_csrf_check($body['csrf_token'] ?? null);

$pares = isset($body['settings']) && is_array($body['settings']) ? $body['settings'] : [];

// Se valida TODO antes de escribir nada: si un campo viene mal, no se guarda un formulario a
// medias que deje al dueño sin saber qué quedó y qué no.
$limpios = [];
foreach (ds_settings_claves() as $clave) {
    if (!array_key_exists($clave, $pares)) continue;
    $r = ds_settings_limpiar($clave, (string) $pares[$clave]);
    if (!$r['ok']) {
        ds_json_error($r['error'], 400);
    }
    $limpios[$clave] = $r['valor'];
}

$pdo  = ds_get_pdo();
$stmt = $pdo->prepare(
    'INSERT INTO settings (clave, valor) VALUES (?, ?)
     ON DUPLICATE KEY UPDATE valor = VALUES(valor)'
);

foreach ($limpios as $clave => $valor) {
    $stmt->execute([$clave, $valor]);
}

ds_json_success(['guardadas' => count($limpios)]);
