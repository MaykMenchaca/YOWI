<?php
declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../lib/Response.php';
require __DIR__ . '/../lib/Session.php';
require __DIR__ . '/../lib/Csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ds_json_error('Método no permitido', 405);
}

$userId = ds_current_user_id();

if ($userId === null) {
    // No es un endpoint protegido: es un check. Nunca devuelve 401.
    ds_json_success(['user' => null, 'csrf_token' => ds_csrf_token()]);
}

$pdo = ds_get_pdo();
$stmt = $pdo->prepare('SELECT id, nombre, email, telefono, terms_accepted_at FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    ds_logout_user();
    ds_json_success(['user' => null, 'csrf_token' => ds_csrf_token()]);
}

ds_json_success([
    'user' => [
        'id' => (int) $user['id'],
        'nombre' => $user['nombre'],
        'email' => $user['email'],
        'telefono' => $user['telefono'],
        // Cuentas creadas vía Google pueden no tener términos aceptados todavía
        // (ver api/auth/google-callback.php) — cuenta.html usa esto para mostrar el
        // aviso obligatorio hasta que los acepte.
        'terms_accepted' => $user['terms_accepted_at'] !== null,
    ],
    'csrf_token' => ds_csrf_token(),
]);
