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
    'SELECT p.id, p.nombre, p.marca, p.precio, p.imagen, p.cantidad, p.unidad, p.stock
     FROM favorites f
     JOIN products p ON p.id = f.product_id
     WHERE f.user_id = ? AND p.activo = 1
     ORDER BY f.created_at DESC'
);
$stmt->execute([$userId]);

$result = array_map(static function (array $p): array {
    return [
        'id' => (int) $p['id'],
        'nombre' => $p['nombre'],
        'marca' => $p['marca'],
        'precio' => (float) $p['precio'],
        'imagen' => $p['imagen'],
        'cantidad' => $p['cantidad'],
        'unidad' => $p['unidad'],
        'stock' => $p['stock'] !== null ? (int) $p['stock'] : null,
    ];
}, $stmt->fetchAll());

ds_json_success($result);
