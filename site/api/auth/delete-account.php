<?php
declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../lib/Response.php';
require __DIR__ . '/../lib/Session.php';
require __DIR__ . '/../lib/Csrf.php';
require __DIR__ . '/../lib/RateLimit.php';
require __DIR__ . '/../lib/AccountDeletion.php';

// Borrado de la PROPIA cuenta (cliente ya logueado). No existía ninguna forma de que un
// cliente eliminara su cuenta y sus datos personales — el aviso de privacidad promete
// ejercer derechos ARCO, pero no había ningún endpoint que lo hiciera cumplir.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') ds_json_error('Método no permitido', 405);
$userId = ds_require_login();

$body = ds_read_json_body();
ds_csrf_check($body['csrf_token'] ?? null);

ds_rate_limit_ip('delete-account', ds_client_ip(), 5, 15);

// Re-pedir la contraseña: borrar la cuenta es irreversible, así que una sesión
// secuestrada (equipo desatendido, cookie robada) no debe poder hacerlo sin saber la
// contraseña real — mismo criterio que activar/desactivar 2FA en el panel admin.
$password = (string) ($body['password'] ?? '');
if ($password === '') ds_json_error('Confirma tu contraseña para eliminar tu cuenta', 400);

$pdo = ds_get_pdo();
$stmt = $pdo->prepare('SELECT email, password_hash FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();
if (!$user) ds_json_error('Cuenta no encontrada', 404);

if (!password_verify($password, (string) $user['password_hash'])) {
    ds_json_error('Contraseña incorrecta', 401);
}

try {
    ds_delete_and_anonymize_user($pdo, $userId, (string) $user['email']);
} catch (Throwable $e) {
    error_log('delete-account.php: ' . $e->getMessage());
    ds_json_error('No se pudo eliminar la cuenta', 500);
}

ds_logout_user();

ds_json_success(['deleted' => true]);
