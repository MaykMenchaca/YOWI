<?php
declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../lib/Response.php';
require __DIR__ . '/../lib/Session.php';
require __DIR__ . '/../lib/Csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ds_json_error('Método no permitido', 405);
}

// CSRF también en logout (evita cierre de sesión forzado por un sitio de terceros).
$body = ds_read_json_body();
ds_csrf_check($body['csrf_token'] ?? null);

ds_logout_user();
ds_json_success(['loggedOut' => true]);
