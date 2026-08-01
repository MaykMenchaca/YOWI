<?php
declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../lib/Response.php';
require __DIR__ . '/../lib/Session.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ds_json_error('Método no permitido', 405);
}

$userId = ds_require_login();
$pdo = ds_get_pdo();

$stmt = $pdo->prepare('SELECT product_id FROM favorites WHERE user_id = ?');
$stmt->execute([$userId]);

$ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

ds_json_success($ids);
