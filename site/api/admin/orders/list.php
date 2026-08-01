<?php
declare(strict_types=1);

require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../lib/Response.php';
require __DIR__ . '/../../lib/AdminSession.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') ds_json_error('Método no permitido', 405);

ds_require_admin();

$page   = max(1, (int)($_GET['page']  ?? 1));
$limit  = min(100, max(1, (int)($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;
$estado = isset($_GET['estado']) ? trim((string)$_GET['estado']) : '';

$validEstados = ['pendiente', 'confirmado', 'enviado', 'entregado', 'cancelado'];
$where  = [];
$params = [];

if ($estado !== '' && in_array($estado, $validEstados, true)) {
    $where[]  = 'o.estado = ?';
    $params[] = $estado;
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$pdo = ds_get_pdo();

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM orders o $whereClause");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT o.id, o.nombre_cliente, o.ciudad, o.telefono, o.direccion_envio, o.total, o.estado, o.created_at,
            u.email AS user_email
     FROM orders o
     LEFT JOIN users u ON u.id = o.user_id
     $whereClause
     ORDER BY o.id DESC
     LIMIT $limit OFFSET $offset"
);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Adjuntar items a cada pedido
$itemStmt = $pdo->prepare(
    'SELECT oi.nombre_producto, oi.precio_unitario, oi.cantidad, oi.subtotal
     FROM order_items oi WHERE oi.order_id = ?'
);

$data = [];
foreach ($orders as $o) {
    $itemStmt->execute([(int) $o['id']]);
    $items = $itemStmt->fetchAll();
    $data[] = [
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
            'precio_unitario' => (float) $i['precio_unitario'],
            'cantidad'        => (int) $i['cantidad'],
            'subtotal'        => (float) $i['subtotal'],
        ], $items),
    ];
}

ds_json_success(['data' => $data, 'total' => $total, 'page' => $page, 'limit' => $limit]);
