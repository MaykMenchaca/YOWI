<?php
declare(strict_types=1);

require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../lib/Response.php';
require __DIR__ . '/../../lib/AdminSession.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') ds_json_error('Método no permitido', 405);

ds_require_rol(DS_ROL_LECTURA);

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if ($id === false || $id <= 0) {
    ds_json_error('id de pedido inválido', 400);
}

$pdo = ds_get_pdo();

$stmt = $pdo->prepare(
    "SELECT o.id, o.nombre_cliente, o.ciudad, o.telefono, o.direccion_envio, o.total, o.estado, o.created_at,
            u.email AS user_email
     FROM orders o
     LEFT JOIN users u ON u.id = o.user_id
     WHERE o.id = ?"
);
$stmt->execute([$id]);
$o = $stmt->fetch();

if (!$o) {
    ds_json_error('Pedido no encontrado', 404);
}

$itemStmt = $pdo->prepare(
    'SELECT oi.nombre_producto, oi.sabor, oi.precio_unitario, oi.cantidad, oi.subtotal
     FROM order_items oi WHERE oi.order_id = ?'
);
$itemStmt->execute([(int) $o['id']]);
$items = $itemStmt->fetchAll();

$data = [
    'id'             => (int) $o['id'],
    'nombre_cliente' => $o['nombre_cliente'],
    'ciudad'         => $o['ciudad'],
    'telefono'       => $o['telefono'],
    'direccion_envio' => $o['direccion_envio'],
    'total'          => (float) $o['total'],
    'estado'         => $o['estado'],
    'created_at'     => $o['created_at'],
    'user_email'     => $o['user_email'],
    'items'          => array_map(static fn($i) => [
        'nombre'          => $i['nombre_producto'],
        'sabor'           => $i['sabor'],
        'precio_unitario' => (float) $i['precio_unitario'],
        'cantidad'        => (int) $i['cantidad'],
        'subtotal'        => (float) $i['subtotal'],
    ], $items),
];

ds_json_success($data);
