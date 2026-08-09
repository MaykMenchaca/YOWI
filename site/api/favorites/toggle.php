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

$userId = ds_require_login();

$body = ds_read_json_body();
ds_csrf_check($body['csrf_token'] ?? null);

// Límite generoso (marcar/desmarcar favoritos es una acción normal y frecuente al
// navegar el catálogo) — solo para que una cuenta no pueda martillar la BD sin freno.
ds_rate_limit_ip('favtoggle', ds_client_ip(), 120, 10);

$productoId = ds_to_positive_int($body['producto_id'] ?? 0);
if ($productoId <= 0) {
    ds_json_error('Producto inválido', 400);
}

$pdo = ds_get_pdo();

// ¿Ya está en favoritos? Si sí, se quita; si no, se agrega (validando el producto).
$exists = $pdo->prepare('SELECT id FROM favorites WHERE user_id = ? AND product_id = ?');
$exists->execute([$userId, $productoId]);

if ($exists->fetch()) {
    $del = $pdo->prepare('DELETE FROM favorites WHERE user_id = ? AND product_id = ?');
    $del->execute([$userId, $productoId]);
    ds_json_success(['favorito' => false]);
}

// Verificar que el producto exista y esté activo antes de insertar.
$check = $pdo->prepare('SELECT id FROM products WHERE id = ? AND activo = 1');
$check->execute([$productoId]);
if (!$check->fetch()) {
    ds_json_error('Producto no disponible', 404);
}

$ins = $pdo->prepare('INSERT IGNORE INTO favorites (user_id, product_id) VALUES (?, ?)');
$ins->execute([$userId, $productoId]);

ds_json_success(['favorito' => true]);
