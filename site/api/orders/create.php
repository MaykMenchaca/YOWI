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

// Tope duro por línea: sin esto, un cliente (o un anónimo sin cuenta) puede pedir
// cantidades absurdas (999999) en una sola línea. El pedido nunca se bloquea por
// disponibilidad (eso se confirma después por WhatsApp) — este tope es solo una
// guardia de abuso, independiente de cualquier control de inventario.
const DS_MAX_QTY_PER_LINE = 100;

$body = ds_read_json_body();
ds_csrf_check($body['csrf_token'] ?? null);

// Anti-abuso: máx. 20 pedidos por IP cada 60 min (frena spam de pedidos).
ds_rate_limit_ip('order', ds_client_ip(), 20, 60);

$nombreCliente = ds_clean_string((string) ($body['nombre_cliente'] ?? ''), 150);
$ciudad = ds_clean_string((string) ($body['ciudad'] ?? ''), 120);
$telefono = ds_clean_string((string) ($body['telefono'] ?? ''), 30);
$direccionEnvio = ds_clean_string((string) ($body['direccion_envio'] ?? ''), 500);
$mensajeWhatsapp = ds_clean_string((string) ($body['mensaje_whatsapp'] ?? ''), 4000);
$privacidadAceptada = (bool) ($body['privacidad_aceptada'] ?? false);
$items = is_array($body['items'] ?? null) ? $body['items'] : [];

if ($nombreCliente === '') {
    ds_json_error('Falta el nombre del cliente', 400);
}
// La casilla de pedido.html ya bloquea el envío en el navegador (assets/js/cart.js), pero
// eso no evita un POST directo sin marcarla. El control real es este: sin el aviso
// aceptado, el pedido transfiere nombre/teléfono/domicilio a WhatsApp sin que quede
// registro de que el cliente lo consintió.
if (!$privacidadAceptada) {
    ds_json_error('Debes aceptar el aviso de privacidad para enviar tu pedido', 400);
}
// El cliente ya exige estos campos antes de enviar (cart.js:validateForm), pero el
// servidor no los pedía — un POST directo podía crear pedidos sin teléfono ni dirección,
// imposibles de contactar o surtir.
if ($telefono === '' || strlen(preg_replace('/\D+/', '', $telefono)) < 10) {
    ds_json_error('Falta un teléfono válido (10 dígitos)', 400);
}
if ($direccionEnvio === '') {
    ds_json_error('Falta la dirección de envío', 400);
}
if ($ciudad === '') {
    ds_json_error('Falta la ciudad', 400);
}
if (empty($items)) {
    ds_json_error('El carrito está vacío', 400);
}
// Techo al número de líneas del pedido. Sin esto, DS_MAX_QTY_PER_LINE es evadible
// repitiendo el mismo producto en muchas líneas (100 × 5000 líneas = 500 000 unidades
// en una sola petición). 50 es generoso para un carrito real (el catálogo cabe varias
// veces).
if (count($items) > 50) {
    ds_json_error('El pedido tiene demasiadas líneas (máximo 50)', 400);
}

// El precio y el nombre SIEMPRE se toman de la BD — nunca se confía en los del cliente.
// El cliente solo aporta qué producto y cuántos; el importe se calcula con el precio real.
$pdo = ds_get_pdo();

// Normalizar los ids/cantidades pedidos ANTES de la transacción (sin tocar BD).
// sabor_id es opcional: si el producto tiene sabores, el cliente eligió uno en la ficha
// (F3.6); si no viene o no es válido para ese producto, el item se descarta más abajo.
//
// IMPORTANTE: se AGREGA por (producto_id, sabor_id) sumando cantidades, en vez de
// guardar cada línea del cliente tal cual. El tope DS_MAX_QTY_PER_LINE se decidió como
// tope "por producto" — aplicarlo por cada entrada del array del cliente lo hacía
// evadible: 5 líneas de 30 del mismo producto sumaban 150 unidades pese al tope de 100.
$acumulado = []; // clave "producto_id|sabor_id" => cantidad total pedida (antes del tope)
foreach ($items as $item) {
    $productoId = ds_to_positive_int($item['producto_id'] ?? 0);
    $cantidadPedida = ds_to_positive_int($item['cantidad'] ?? 0);
    if ($productoId <= 0 || $cantidadPedida <= 0) {
        continue;
    }
    $saborIdRaw = $item['sabor_id'] ?? null;
    $saborId = $saborIdRaw !== null ? ds_to_positive_int($saborIdRaw) : 0;

    $clave = $productoId . '|' . $saborId;
    if (!isset($acumulado[$clave])) {
        $acumulado[$clave] = ['producto_id' => $productoId, 'sabor_id' => $saborId, 'cantidad' => 0];
    }
    $acumulado[$clave]['cantidad'] += $cantidadPedida;
}

$requested = [];
$ajustes = []; // lo que se descartó o recortó, para devolverlo honesto al cliente
foreach ($acumulado as $linea) {
    $cantidadPedida = $linea['cantidad'];
    $cantidad = min($cantidadPedida, DS_MAX_QTY_PER_LINE);
    if ($cantidad < $cantidadPedida) {
        $ajustes[] = [
            'producto_id' => $linea['producto_id'],
            'sabor_id' => $linea['sabor_id'] > 0 ? $linea['sabor_id'] : null,
            'motivo' => 'limite_cantidad',
            'cantidad_pedida' => $cantidadPedida,
            'cantidad_final' => $cantidad,
        ];
    }
    $requested[] = ['producto_id' => $linea['producto_id'], 'cantidad' => $cantidad, 'sabor_id' => $linea['sabor_id'] > 0 ? $linea['sabor_id'] : null];
}

if (empty($requested)) {
    ds_json_error('El carrito está vacío', 400);
}

$userId = ds_current_user_id();

$pdo->beginTransaction();
try {
    // Lectura de producto/sabor DENTRO de la transacción: nombre y precio (si el sabor
    // tiene uno propio) siempre se toman de la BD, nunca del cliente. No hay nada que
    // bloquear ni descontar — la disponibilidad se confirma después por WhatsApp.
    $lookup = $pdo->prepare('SELECT nombre, precio FROM products WHERE id = ? AND activo = 1');
    $lookupFlavor = $pdo->prepare('SELECT id, nombre FROM product_flavors WHERE id = ? AND product_id = ? AND activo = 1');
    $countFlavors = $pdo->prepare('SELECT COUNT(*) FROM product_flavors WHERE product_id = ? AND activo = 1');

    $cleanItems = [];
    $total = 0.0;

    foreach ($requested as $req) {
        $productoId = $req['producto_id'];
        $cantidad = $req['cantidad'];
        $saborId = $req['sabor_id'];

        $lookup->execute([$productoId]);
        $producto = $lookup->fetch();
        if (!$producto) {
            // producto inexistente, inactivo o eliminado: se descarta el item entero.
            // No hay nombre que mostrar (no lo encontramos), así que se identifica por id.
            $ajustes[] = ['producto_id' => $productoId, 'sabor_id' => $saborId, 'nombre' => null,
                'motivo' => 'no_disponible', 'cantidad_pedida' => $cantidad, 'cantidad_final' => 0];
            continue;
        }

        $sabor = null;
        if ($saborId !== null) {
            $lookupFlavor->execute([$saborId, $productoId]);
            $sabor = $lookupFlavor->fetch() ?: null;
            if ($sabor === null) {
                // el sabor no existe, está inactivo o no es de este producto
                $ajustes[] = ['producto_id' => $productoId, 'sabor_id' => $saborId, 'nombre' => $producto['nombre'],
                    'motivo' => 'sabor_no_disponible', 'cantidad_pedida' => $cantidad, 'cantidad_final' => 0];
                continue;
            }
        } else {
            // Si el producto tiene sabores activos, no se puede vender "a ciegas" sin
            // que el cliente haya elegido uno (F3.6 lo exige en la ficha; esto es la
            // guardia del servidor por si algo manda el pedido sin pasar por ahí).
            $countFlavors->execute([$productoId]);
            if ((int) $countFlavors->fetchColumn() > 0) {
                $ajustes[] = ['producto_id' => $productoId, 'sabor_id' => null, 'nombre' => $producto['nombre'],
                    'motivo' => 'falta_elegir_sabor', 'cantidad_pedida' => $cantidad, 'cantidad_final' => 0];
                continue;
            }
        }

        $nombreParaAjuste = $producto['nombre'] . ($sabor !== null ? ' (' . $sabor['nombre'] . ')' : '');

        // Precio único: el sabor es solo un nombre, el precio siempre es el del producto.
        $precioUnitario = (float) $producto['precio'];

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
        ];
    }

    if (empty($cleanItems)) {
        $pdo->rollBack();
        // No es "carrito vacío" a secas: se sabe exactamente por qué se vació (producto
        // ya no existe, sabor inactivo, etc.) — se manda el detalle para que el front dé un mensaje
        // honesto en vez del genérico, que confundía al cliente (veía su ítem en pantalla
        // mientras el servidor decía "vacío").
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => false,
            'error' => 'Ningún producto de tu carrito sigue disponible. Revisa tu carrito e intenta de nuevo.',
            'ajustes' => $ajustes,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $insertOrder = $pdo->prepare(
        'INSERT INTO orders (user_id, nombre_cliente, ciudad, telefono, direccion_envio, total, mensaje_whatsapp, privacidad_aceptada_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
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
        'INSERT INTO order_items (order_id, producto_id, sabor_id, nombre_producto, sabor, precio_unitario, cantidad, subtotal)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    foreach ($cleanItems as $item) {
        $insertItem->execute([
            $orderId,
            $item['producto_id'],
            $item['sabor_id'],
            $item['nombre_producto'],
            $item['sabor'],
            $item['precio_unitario'],
            $item['cantidad'],
            $item['subtotal'],
        ]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('orders/create.php: ' . $e->getMessage());
    ds_json_error('No se pudo registrar el pedido', 500);
}

// Se devuelven los items realmente guardados + los ajustes hechos (si los hubo), para
// que el front reconstruya el mensaje de WhatsApp y el resumen en pantalla desde ESTO,
// no desde el carrito local — así lo que le llega al vendedor por WhatsApp siempre
// coincide con lo que quedó en la base de datos.
ds_json_success([
    'order_id' => $orderId,
    'total' => round($total, 2),
    'items' => array_map(static fn(array $i): array => [
        'producto_id' => $i['producto_id'],
        'sabor_id' => $i['sabor_id'],
        'nombre_producto' => $i['nombre_producto'],
        'sabor' => $i['sabor'],
        'precio_unitario' => $i['precio_unitario'],
        'cantidad' => $i['cantidad'],
        'subtotal' => $i['subtotal'],
    ], $cleanItems),
    'ajustes' => $ajustes,
], 201);
