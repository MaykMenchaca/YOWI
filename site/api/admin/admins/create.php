<?php
declare(strict_types=1);

require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../lib/Response.php';
require __DIR__ . '/../../lib/AdminSession.php';
require __DIR__ . '/../../lib/Validate.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') ds_json_error('Método no permitido', 405);
ds_require_rol(DS_ROL_DUENO);

$body = ds_read_json_body();
ds_admin_csrf_check($body['csrf_token'] ?? null);

$nombre = ds_clean_string((string) ($body['nombre'] ?? ''), 150);
$email  = ds_validate_email((string) ($body['email'] ?? ''));
$pass   = (string) ($body['password'] ?? '');
$rol    = (string) ($body['rol'] ?? '');

if ($nombre === '' || $email === null) {
    ds_json_error('Nombre y correo son requeridos', 400);
}
$email = strtolower($email);

// Whitelist a mano: Validate.php no tiene validador de enum (mismo patrón que
// orders/update-status.php con los estados de pedido).
if (!in_array($rol, [DS_ROL_LECTURA, DS_ROL_OPERADOR, DS_ROL_DUENO], true)) {
    ds_json_error('Rol inválido', 400);
}
// Misma política que register.php y create-admin.php (ds_validate_password en Validate.php).
if (($errPass = ds_validate_password($pass)) !== null) {
    ds_json_error($errPass, 400);
}

$pdo   = ds_get_pdo();
$check = $pdo->prepare('SELECT id FROM admins WHERE email = ?');
$check->execute([$email]);
if ($check->fetch()) {
    ds_json_error('Ya existe una cuenta con ese correo', 409);
}

$hash = password_hash($pass, PASSWORD_DEFAULT);
$stmt = $pdo->prepare(
    'INSERT INTO admins (nombre, email, password_hash, rol) VALUES (?, ?, ?, ?)'
);
$stmt->execute([$nombre, $email, $hash, $rol]);
$newId = (int) $pdo->lastInsertId();

// activo=1 y totp_enabled=0 quedan en su default de columna: la cuenta nace activa pero
// SIN 2FA — el guardián ya obliga a enrolarlo antes de poder operar el panel (auditoría
// de seguridad de ayer), así que no hace falta repetir esa exigencia aquí.
ds_admin_log('admins/create', "admin_id={$newId} email={$email} rol={$rol}");

ds_json_success(['id' => $newId], 201);
