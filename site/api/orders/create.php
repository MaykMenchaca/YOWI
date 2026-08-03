<?php
declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../lib/Response.php';
require __DIR__ . '/../lib/Session.php';
require __DIR__ . '/../lib/Csrf.php';
require __DIR__ . '/../lib/Validate.php';
require __DIR__ . '/../lib/RateLimit.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ds_json_error('Método no permitido', 405);
}

$body = ds_read_json_body();
ds_csrf_check($body['csrf_token'] ?? null);

// Anti-abuso: máx. 20 pedidos por IP cada 60 min (frena spam de pedidos).
ds_rate_limit_ip('order', ds_client_ip(), 20, 60);

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

// Normalizar los ids/cantidades pedidos ANTES de la transacción (sin tocar BD).
// sabor_id es opcional: si el producto tiene sabores, el cliente eligió uno en la ficha
// (F3.6); si no viene o no es válido para ese producto, el item se descarta más abajo.
$requested = [];
foreach ($items as $item) {
    $productoId = ds_to_positive_int($item['producto_id'] ?? 0);
    $cantidad = ds_to_positive_int($item['cantidad'] ?? 0);
    if ($productoId <= 0 || $cantidad <= 0) {
        continue;
    }
    $saborIdRaw = $item['sabor_id'] ?? null;
    $saborId = $saborIdRaw !== null ? ds_to_positive_int($saborIdRaw) : 0;
    $requested[] = ['producto_id' => $productoId, 'cantidad' => $cantidad, 'sabor_id' => $saborId > 0 ? $saborId : null];
}

if (empty($requested)) {
    ds_json_error('El carrito está vacío', 400);
}

$userId = ds_current_user_id();

$pdo->beginTransaction();
try {
    // Lectura de producto DENTRO de la transacción y con bloqueo de fila (FOR UPDATE)
    // para evitar sobreventa bajo concurrencia. Igual para el sabor elegido: su stock y
    // su precio (si tiene uno propio) también se bloquean y se leen de la BD, nunca del
    // cliente.
    $lookup = $pdo->prepare('SELECT nombre, precio, stock FROM products WHERE id = ? AND activo = 1 FOR UPDATE');
    $dec = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?');
    $lookupFlavor = $pdo->prepare('SELECT id, nombre, stock, precio FROM product_flavors WHERE id = ? AND product_id = ? AND activo = 1 FOR UPDATE');
    $countFlavors = $pdo->prepare('SELECT COUNT(*) FROM product_flavors WHERE product_id = ? AND activo = 1');
    $decFlavor = $pdo->prepare('UPDATE product_flavors SET stock = stock - ? WHERE id = ? AND stock >= ?');

    $cleanItems = [];
    $total = 0.0;

    foreach ($requested as $req) {
        $productoId = $req['producto_id'];
        $cantidad = $req['cantidad'];
        $saborId = $req['sabor_id'];

        $lookup->execute([$productoId]);
        $producto = $lookup->fetch();
        if (!$producto) {
            continue; // producto inexistente, inactivo o eliminado: se ignora
        }

        $sabor = null;
        if ($saborId !== null) {
            $lookupFlavor->execute([$saborId, $productoId]);
            $sabor = $lookupFlavor->fetch() ?: null;
            if ($sabor === null) {
                continue; // el sabor no existe, está inactivo o no es de este producto
            }
        } else {
            // Si el producto tiene sabores activos, no se puede vender "a ciegas" sin
            // que el cliente haya elegido uno (F3.6 lo exige en la ficha; esto es la
            // guardia del servidor por si algo manda el pedido sin pasar por ahí).
            $countFlavors->execute([$productoId]);
            if ((int) $countFlavors->fetchColumn() > 0) {
                continue;
            }
        }

        if ($sabor !== null) {
            // Modelo de stock por sabor: misma semántica que products.stock (NULL = sin
            // control, 0 = agotado). El precio del sabor manda si tiene uno propio.
            $stockSabor = $sabor['stock'];
            if ($stockSabor !== null) {
                $stockSabor = (int) $stockSabor;
                if ($stockSabor <= 0) {
                    continue; // ese sabor está agotado: se descarta el item
                }
                if ($cantidad > $stockSabor) {
                    $cantidad = $stockSabor; // topa al disponible de ESE sabor
                }
            }
            $precioUnitario = $sabor['precio'] !== null ? (float) $sabor['precio'] : (float) $producto['precio'];
            $controlaStock = $stockSabor !== null;
        } else {
            // Producto sin sabores: modelo de stock de siempre, a nivel producto.
            $stock = $producto['stock']; // null | int
            if ($stock !== null) {
                $stock = (int) $stock;
                if ($stock <= 0) {
                    continue; // agotado: se descarta el item
                }
                if ($cantidad > $stock) {
                    $cantidad = $stock; // topa al disponible
                }
            }
            $precioUnitario = (float) $producto['precio'];
            $controlaStock = $stock !== null;
        }

        $subtotal = round($precioUnitario * $cantidad, 2);
        $total += $subtotal;
        $cleanItems[] = [
            'producto_id' => $productoId,
            'sabor_id' => $sabor['id'] ?? null,
            'nombre_producto' => $producto['nombre'],
            'sabor' => $sabor['nombre'] ?? null,
            'precio_unitario' => $precioUnitario,
            'cantidad' => $cantidad,
            'subtotal' => $subtotal,
            'controla_stock' => $controlaStock,
        ];
    }

    if (empty($cleanItems)) {
        $pdo->rollBack();
        ds_json_error('El carrito está vacío', 400);
    }

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
        'INSERT INTO order_items (order_id, producto_id, nombre_producto, sabor, precio_unitario, cantidad, subtotal)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    foreach ($cleanItems as $item) {
        $insertItem->execute([
            $orderId,
            $item['producto_id'],
            $item['nombre_producto'],
            $item['sabor'],
            $item['precio_unitario'],
            $item['cantidad'],
            $item['subtotal'],
        ]);
        // Descontar inventario con guardia; si otra transacción ganó la última unidad,
        // rowCount() == 0 y se aborta todo el pedido (rollBack). Con sabor, el descuento
        // es sobre el stock DE ESE SABOR, no del producto (el producto no tiene stock
        // propio que controlar cuando ya se vende por sabor).
        if ($item['controla_stock']) {
            if ($item['sabor_id'] !== null) {
                $decFlavor->execute([$item['cantidad'], $item['sabor_id'], $item['cantidad']]);
                if ($decFlavor->rowCount() === 0) {
                    throw new RuntimeException('stock insuficiente');
                }
            } else {
                $dec->execute([$item['cantidad'], $item['producto_id'], $item['cantidad']]);
                if ($dec->rowCount() === 0) {
                    throw new RuntimeException('stock insuficiente');
                }
            }
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('orders/create.php: ' . $e->getMessage());
    ds_json_error('No se pudo registrar el pedido', 500);
}

ds_json_success(['order_id' => $orderId], 201);
