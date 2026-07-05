<?php
declare(strict_types=1);

require __DIR__ . '/../lib/Response.php';
require __DIR__ . '/../lib/Session.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ds_json_error('Método no permitido', 405);
}

ds_logout_user();
ds_json_success(['loggedOut' => true]);
