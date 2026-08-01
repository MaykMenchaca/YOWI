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

$body = ds_read_json_body();
ds_csrf_check($body['csrf_token'] ?? null);

// Anti-abuso: máx. 10 registros por IP cada 60 min (frena spam y enumeración masiva).
ds_rate_limit_ip('register', ds_client_ip(), 10, 60);

$nombre = ds_clean_string((string) ($body['nombre'] ?? ''), 150);
$email = ds_validate_email((string) ($body['email'] ?? ''));
$telefono = ds_clean_string((string) ($body['telefono'] ?? ''), 30);
$password = (string) ($body['password'] ?? '');
$confirmPassword = (string) ($body['confirm_password'] ?? '');

if ($nombre === '' || $email === null) {
    ds_json_error('Nombre o correo inválido', 400);
}
if (strlen($password) < 8) {
    ds_json_error('La contraseña debe tener al menos 8 caracteres', 400);
}
if ($password !== $confirmPassword) {
    ds_json_error('Las contraseñas no coinciden', 400);
}

$pdo = ds_get_pdo();

$check = $pdo->prepare('SELECT id FROM users WHERE email = ?');
$check->execute([$email]);
if ($check->fetch()) {
    ds_json_error('El correo ya está registrado', 409);
}

$hash = password_hash($password, PASSWORD_DEFAULT);

$insert = $pdo->prepare(
    'INSERT INTO users (nombre, email, telefono, password_hash) VALUES (?, ?, ?, ?)'
);
$insert->execute([$nombre, $email, $telefono !== '' ? $telefono : null, $hash]);

$userId = (int) $pdo->lastInsertId();
ds_login_user($userId);

ds_json_success(['id' => $userId, 'nombre' => $nombre, 'email' => $email], 201);
