<?php
declare(strict_types=1);

require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../lib/Response.php';
require __DIR__ . '/../../lib/AdminSession.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') ds_json_error('Método no permitido', 405);
ds_require_rol(DS_ROL_OPERADOR);

$body = ds_read_json_body();
ds_admin_csrf_check($body['csrf_token'] ?? null);

$id = (int)($body['id'] ?? 0);
if ($id <= 0) ds_json_error('ID requerido', 400);

$pdo = ds_get_pdo();

// Bloquear si tiene productos activos
$check = $pdo->prepare('SELECT COUNT(*) FROM products WHERE category_id = ? AND activo = 1');
$check->execute([$id]);
if ((int) $check->fetchColumn() > 0) {
    ds_json_error('No se puede eliminar: la categoría tiene productos activos', 409);
}

$stmt = $pdo->prepare('DELETE FROM categories WHERE id = ?');
$stmt->execute([$id]);

ds_json_success(['deleted' => $stmt->rowCount() > 0]);
