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

$pdo  = ds_get_pdo();
$stmt = $pdo->prepare('UPDATE orders SET estado = ? WHERE id = ?');
$stmt->execute([$estado, $id]);

ds_json_success(['updated' => $stmt->rowCount() > 0, 'estado' => $estado]);
