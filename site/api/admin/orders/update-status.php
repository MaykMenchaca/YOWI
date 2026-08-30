<?php
declare(strict_types=1);

require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../lib/Response.php';
require __DIR__ . '/../../lib/AdminSession.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') ds_json_error('Método no permitido', 405);
ds_require_rol(DS_ROL_OPERADOR);

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
    $orderStmt = $pdo->prepare('SELECT estado FROM orders WHERE id = ?');
    $orderStmt->execute([$id]);
    $order = $orderStmt->fetch();
    if (!$order) {
        $pdo->rollBack();
        ds_json_error('Pedido no encontrado', 404);
    }

    $upd = $pdo->prepare('UPDATE orders SET estado = ? WHERE id = ?');
    $upd->execute([$estado, $id]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('admin/orders/update-status.php: ' . $e->getMessage());
    ds_json_error('No se pudo actualizar el pedido', 500);
}

ds_json_success(['updated' => true, 'estado' => $estado]);
