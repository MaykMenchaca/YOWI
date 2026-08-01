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

$userId = ds_require_login();

$body = ds_read_json_body();
ds_csrf_check($body['csrf_token'] ?? null);

$id = ds_to_positive_int($body['id'] ?? 0);
if ($id <= 0) {
    ds_json_error('Dirección inválida', 400);
}

$pdo = ds_get_pdo();
$del = $pdo->prepare('DELETE FROM user_addresses WHERE id = ? AND user_id = ?');
$del->execute([$id, $userId]);

if ($del->rowCount() === 0) {
    ds_json_error('Dirección no encontrada', 404);
}

ds_json_success(['deleted' => true]);
