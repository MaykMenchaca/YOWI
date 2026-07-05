<?php
declare(strict_types=1);

require __DIR__ . '/../../lib/Response.php';
require __DIR__ . '/../../lib/AdminSession.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') ds_json_error('Método no permitido', 405);

$body = ds_read_json_body();
ds_admin_csrf_check($body['csrf_token'] ?? null);

ds_logout_admin();

ds_json_success(['logged_out' => true]);
