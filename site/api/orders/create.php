<?php
declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../lib/Response.php';
require __DIR__ . '/../lib/Session.php';
require __DIR__ . '/../lib/Csrf.php';
require __DIR__ . '/../lib/Validate.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ds_json_error('Método no permitido', 405);
}

$body = ds_read_json_body();
ds_csrf_check($body['csrf_token'] ?? null);

$nombreCliente = ds_clean_string((string) ($body['nombre_cliente'] ?? ''), 150);
$ciudad = ds_clean_string((string) ($body['ciudad'] ?? ''), 120);
$telefono = ds_clean_string((string) ($body['telefono'] ?? ''), 30);
$direccionEnvio = ds_clean_string((string) ($body['direccion_envio'] ?? ''), 500);
$mensajeWhatsapp = ds_clean_string((string) ($body['mensaje_whatsapp'] ?? ''), 4000);
$items = is_array($body['items'] ?? null) ? $body['items'] : [];

if ($nombreCliente === '') {
    ds_json_error('Falta el nombre del cliente', 400);
}
if (empty($items)) {
    ds_json_error('El carrito está vacío', 400);
}

// El precio y el nombre SIEMPRE se toman de la BD — nunca se confía en los del cliente.
// El cliente solo aporta qué producto y cuántos; el importe se calcula con el precio real.
$pdo = ds_get_pdo();

$cleanItems = [];
$total = 0.0;
$lookup = $pdo->prepare('SELECT nombre, precio, stock FROM products WHERE id = ? AND activo = 1');
foreach ($items as $item) {
    $productoId = ds_to_positive_int($item['producto_id'] ?? 0);
    $cantidad = ds_to_positive_int($item['cantidad'] ?? 0);

    if ($productoId <= 0 || $cantidad <= 0) {
        continue;
    }

    $lookup->execute([$productoId]);
    $producto = $lookup->fetch();
    if (!$producto) {
        continue; // producto inexistente, inactivo o eliminado: se ignora
    }

    // Si el producto tiene control de stock (>0), limitar la cantidad al disponible.
    $stock = (int) $producto['stock'];
    if ($stock > 0 && $cantidad > $stock) {
        $cantidad = $stock;
    }

    $precioUnitario = (float) $producto['precio'];
    $subtotal = round($precioUnitario * $cantidad, 2);
    $total += $subtotal;
    $cleanItems[] = [
        'producto_id' => $productoId,
        'nombre_producto' => $producto['nombre'],
        'precio_unitario' => $precioUnitario,
        'cantidad' => $cantidad,
        'subtotal' => $subtotal,
    ];
}

if (empty($cleanItems)) {
    ds_json_error('El carrito está vacío', 400);
}

$userId = ds_current_user_id();

$pdo->beginTransaction();
try {
    $insertOrder = $pdo->prepare(
        'INSERT INTO orders (user_id, nombre_cliente, ciudad, telefono, direccion_envio, total, mensaje_whatsapp)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $insertOrder->execute([
        $userId,
        $nombreCliente,
        $ciudad !== '' ? $ciudad : null,
        $telefono !== '' ? $telefono : null,
        $direccionEnvio !== '' ? $direccionEnvio : null,
        round($total, 2),
        $mensajeWhatsapp !== '' ? $mensajeWhatsapp : null,
    ]);
    $orderId = (int) $pdo->lastInsertId();

    $insertItem = $pdo->prepare(
        'INSERT INTO order_items (order_id, producto_id, nombre_producto, precio_unitario, cantidad, subtotal)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    foreach ($cleanItems as $item) {
        $insertItem->execute([
            $orderId,
            $item['producto_id'],
            $item['nombre_producto'],
            $item['precio_unitario'],
            $item['cantidad'],
            $item['subtotal'],
        ]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('orders/create.php: ' . $e->getMessage());
    ds_json_error('No se pudo registrar el pedido', 500);
}

ds_json_success(['order_id' => $orderId], 201);
