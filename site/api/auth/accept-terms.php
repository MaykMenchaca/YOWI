<?php
declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../lib/Response.php';
require __DIR__ . '/../lib/Session.php';
require __DIR__ . '/../lib/Csrf.php';
require __DIR__ . '/../lib/RateLimit.php';

// POST /api/auth/accept-terms.php — confirma el checkbox de términos que una cuenta
// creada vía Google se saltó al registrarse (ver api/auth/google-callback.php). No
// recibe datos del cliente, solo confirma: no hay nada que validar más que la sesión.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ds_json_error('Método no permitido', 405);
}

$userId = ds_require_login();

$body = ds_read_json_body();
ds_csrf_check($body['csrf_token'] ?? null);

ds_rate_limit_ip('accept-terms', ds_client_ip(), 30, 60);

$pdo = ds_get_pdo();
$pdo->prepare('UPDATE users SET terms_accepted_at = NOW() WHERE id = ?')->execute([$userId]);

ds_json_success(['terms_accepted' => true]);
