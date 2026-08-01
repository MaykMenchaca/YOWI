<?php
declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../lib/Response.php';
require __DIR__ . '/../lib/Session.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ds_json_error('Método no permitido', 405);
}

$userId = ds_require_login();
$pdo = ds_get_pdo();

$stmt = $pdo->prepare(
    'SELECT id, etiqueta, nombre, telefono, calle, colonia, cp, ciudad, estado, referencias, es_predeterminada
     FROM user_addresses WHERE user_id = ?
     ORDER BY es_predeterminada DESC, id DESC'
);
$stmt->execute([$userId]);

$result = array_map(static function (array $a): array {
    return [
        'id' => (int) $a['id'],
        'etiqueta' => $a['etiqueta'],
        'nombre' => $a['nombre'],
        'telefono' => $a['telefono'],
        'calle' => $a['calle'],
        'colonia' => $a['colonia'],
        'cp' => $a['cp'],
        'ciudad' => $a['ciudad'],
        'estado' => $a['estado'],
        'referencias' => $a['referencias'],
        'es_predeterminada' => (int) $a['es_predeterminada'] === 1,
    ];
}, $stmt->fetchAll());

ds_json_success($result);
