<?php
declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../lib/Response.php';
require __DIR__ . '/../lib/Session.php';
require __DIR__ . '/../lib/Csrf.php';
require __DIR__ . '/../lib/Validate.php';
require __DIR__ . '/../lib/RateLimit.php';

// Cambio de la PROPIA contraseña (cliente ya logueado). Antes de esto no existía ninguna
// forma de que un cliente cambiara su contraseña por HTTP, y con MAIL_TRANSPORT=none
// tampoco funciona "olvidé mi contraseña" — sin este endpoint, un cliente que quisiera
// cambiar su contraseña por precaución no tenía cómo hacerlo. Espejo exacto de
// site/api/admin/auth/change-password.php, adaptado a la tabla users.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') ds_json_error('Método no permitido', 405);
$userId = ds_require_login();

$body = ds_read_json_body();
ds_csrf_check($body['csrf_token'] ?? null);

ds_rate_limit_ip('pwchange-cliente', ds_client_ip(), 10, 15);

$actual = (string) ($body['password_actual'] ?? '');
$nueva  = (string) ($body['password_nueva'] ?? '');

if ($actual === '') ds_json_error('Ingresa tu contraseña actual', 400);
if (($errPass = ds_validate_password($nueva)) !== null) ds_json_error($errPass, 400);

$pdo  = ds_get_pdo();
$stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();
if (!$user) ds_json_error('Cuenta no encontrada', 404);

if (!password_verify($actual, (string) $user['password_hash'])) {
    ds_json_error('Tu contraseña actual no es correcta', 401);
}

$hash = password_hash($nueva, PASSWORD_DEFAULT);
$upd  = $pdo->prepare('UPDATE users SET password_hash = ?, password_changed_at = NOW() WHERE id = ?');
$upd->execute([$hash, $userId]);

// Re-sellar login_time: sin esto, ds_session_check_password_change() (lib/Session.php)
// expulsaría al propio usuario en su siguiente petición, justo tras cambiar su
// contraseña con éxito — mismo detalle que ya resolvió admin/auth/change-password.php.
$_SESSION['user_login_time'] = time();

ds_json_success(['updated' => true]);
