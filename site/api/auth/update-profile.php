<?php
declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../lib/Response.php';
require __DIR__ . '/../lib/Session.php';
require __DIR__ . '/../lib/Csrf.php';
require __DIR__ . '/../lib/Validate.php';
require __DIR__ . '/../lib/RateLimit.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') ds_json_error('Método no permitido', 405);

$userId = ds_require_login();

$body = ds_read_json_body();
ds_csrf_check($body['csrf_token'] ?? null);

// Anti-abuso: máx. 30 actualizaciones de perfil por IP cada 60 min.
ds_rate_limit_ip('profile', ds_client_ip(), 30, 60);

$nombre   = ds_clean_string((string) ($body['nombre'] ?? ''), 150);
$telefono = ds_clean_string((string) ($body['telefono'] ?? ''), 30);

if ($nombre === '') {
    ds_json_error('El nombre no puede estar vacío', 400);
}

$pdo  = ds_get_pdo();
$stmt = $pdo->prepare('UPDATE users SET nombre = ?, telefono = ? WHERE id = ?');
$stmt->execute([$nombre, $telefono !== '' ? $telefono : null, $userId]);

ds_json_success(['nombre' => $nombre, 'telefono' => $telefono]);
