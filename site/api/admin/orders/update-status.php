<?php
declare(strict_types=1);

require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../lib/Response.php';
require __DIR__ . '/../../lib/AdminSession.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') ds_json_error('Método no permitido', 405);
ds_require_admin();

$body = ds_read_json_body();
ds_admin_csrf_check($body['csrf_token'] ?? null);

$id     = (int)($body['id'] ?? 0);
$estado = trim((string)($body['estado'] ?? ''));

$validEstados = ['pendiente', 'confirmado', 'enviado', 'entregado', 'cancelado'];
if ($id <= 0 || !in_array($estado, $validEstados, true)) {
    ds_json_error('id y estado válido son requeridos', 400);
}

$pdo = ds_get_pdo();
$pdo->beginTransaction();
try {
    // FOR UPDATE: evita que dos cambios de estado del mismo pedido se pisen y reponer
    // o descontar el stock dos veces por una carrera entre dos clics/pestañas del admin.
    $orderStmt = $pdo->prepare('SELECT estado, stock_repuesto FROM orders WHERE id = ? FOR UPDATE');
    $orderStmt->execute([$id]);
    $order = $orderStmt->fetch();
    if (!$order) {
        $pdo->rollBack();
        ds_json_error('Pedido no encontrado', 404);
    }

    $estadoAnterior = $order['estado'];
    $yaRepuesto = (bool) $order['stock_repuesto'];
    $nuevoStockRepuesto = $yaRepuesto;

    // Al cancelar (y solo la primera vez que se cancela — stock_repuesto evita que
    // cancelar dos veces reponga doble): devolver al inventario lo que este pedido
    // había descontado. Solo se tocan renglones con stock IS NOT NULL, que es
    // exactamente la misma condición que create.php usó para decidir si descontaba
    // o no ("sin control de inventario" nunca se descontó, así que tampoco se repone).
    if ($estado === 'cancelado' && $estadoAnterior !== 'cancelado' && !$yaRepuesto) {
        ds_orders_ajustar_stock($pdo, $id, +1);
        $nuevoStockRepuesto = true;
    } elseif ($estado !== 'cancelado' && $estadoAnterior === 'cancelado' && $yaRepuesto) {
        // Reactivar un pedido cancelado: si se le había devuelto el stock, hay que
        // volver a descontarlo — si no, ese inventario quedaría "flotando" duplicado
        // (una vez disponible en el catálogo, otra vez comprometido con este pedido).
        // Best-effort con guardia (stock >= cantidad): si algo se agotó mientras tanto,
        // ese renglón puntual se queda sin descontar en vez de bloquear la reactivación
        // completa — es una situación rara (reactivar un pedido cancelado) y preferible
        // a dejar al admin sin forma de corregir un clic equivocado.
        ds_orders_ajustar_stock($pdo, $id, -1);
        $nuevoStockRepuesto = false;
    }

    $upd = $pdo->prepare('UPDATE orders SET estado = ?, stock_repuesto = ? WHERE id = ?');
    $upd->execute([$estado, $nuevoStockRepuesto ? 1 : 0, $id]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('admin/orders/update-status.php: ' . $e->getMessage());
    ds_json_error('No se pudo actualizar el pedido', 500);
}

ds_json_success(['updated' => true, 'estado' => $estado]);

/**
 * Suma (signo +1) o resta (signo -1) el stock de cada línea de un pedido, a nivel
 * producto o de sabor según corresponda. Debe llamarse DENTRO de una transacción ya
 * abierta (no abre ni cierra la suya). El signo -1 usa una guardia `stock >= cantidad`
 * igual que create.php: si no alcanza, esa línea puntual simplemente no se descuenta
 * (no revienta la transacción completa).
 */
function ds_orders_ajustar_stock(PDO $pdo, int $orderId, int $signo): void
{
    $items = $pdo->prepare('SELECT producto_id, sabor_id, sabor, cantidad FROM order_items WHERE order_id = ?');
    $items->execute([$orderId]);

    $sumarProducto = $pdo->prepare('UPDATE products SET stock = stock + ? WHERE id = ? AND stock IS NOT NULL');
    $restarProducto = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ? AND stock IS NOT NULL AND stock >= ?');
    $sumarSabor = $pdo->prepare('UPDATE product_flavors SET stock = stock + ? WHERE id = ? AND stock IS NOT NULL');
    $restarSabor = $pdo->prepare('UPDATE product_flavors SET stock = stock - ? WHERE id = ? AND stock IS NOT NULL AND stock >= ?');

    foreach ($items->fetchAll() as $item) {
        $cantidad = (int) $item['cantidad'];
        if ($item['sabor_id'] !== null) {
            if ($signo > 0) {
                $sumarSabor->execute([$cantidad, $item['sabor_id']]);
            } else {
                $restarSabor->execute([$cantidad, $item['sabor_id'], $cantidad]);
            }
        } elseif ($item['producto_id'] !== null && $item['sabor'] === null) {
            // Solo se toca el stock a nivel producto si esta línea genuinemente nunca
            // tuvo sabor. Si `sabor` trae texto pero `sabor_id` quedó NULL (pedido de
            // antes de esta migración cuyo sabor ya no se pudo emparejar por nombre —
            // se renombró o se borró), no hay forma segura de saber qué fila de
            // product_flavors le corresponde: se omite en vez de adivinar y tocar el
            // stock del producto equivocado.
            if ($signo > 0) {
                $sumarProducto->execute([$cantidad, $item['producto_id']]);
            } else {
                $restarProducto->execute([$cantidad, $item['producto_id'], $cantidad]);
            }
        }
    }
}
