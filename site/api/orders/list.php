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

$ordersStmt = $pdo->prepare(
    'SELECT id, total, estado, created_at FROM orders WHERE user_id = ? ORDER BY created_at DESC'
);
$ordersStmt->execute([$userId]);
$orders = $ordersStmt->fetchAll();

if (empty($orders)) {
    ds_json_success([]);
}

$orderIds = array_column($orders, 'id');
$placeholders = implode(',', array_fill(0, count($orderIds), '?'));
$itemsStmt = $pdo->prepare(
    "SELECT order_id, nombre_producto, precio_unitario, cantidad
     FROM order_items WHERE order_id IN ($placeholders)"
);
$itemsStmt->execute($orderIds);
$itemsByOrder = [];
foreach ($itemsStmt->fetchAll() as $item) {
    $itemsByOrder[(int) $item['order_id']][] = [
        'nombre_producto' => $item['nombre_producto'],
        'precio_unitario' => (float) $item['precio_unitario'],
        'cantidad' => (int) $item['cantidad'],
    ];
}

$result = array_map(static function (array $order) use ($itemsByOrder): array {
    return [
        'id' => (int) $order['id'],
        'created_at' => $order['created_at'],
        'total' => (float) $order['total'],
        'estado' => $order['estado'],
        'items' => $itemsByOrder[(int) $order['id']] ?? [],
    ];
}, $orders);

ds_json_success($result);
