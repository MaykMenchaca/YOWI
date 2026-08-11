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

$email = ds_validate_email((string) ($body['email'] ?? ''));
$password = (string) ($body['password'] ?? '');

if ($email === null || $password === '') {
    ds_json_error('Credenciales inválidas', 401);
}

$ip = ds_client_ip();
ds_login_throttle_check('cliente', $email, $ip);

$pdo = ds_get_pdo();
$stmt = $pdo->prepare('SELECT id, nombre, email, password_hash FROM users WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch();

// Si el usuario no existe, gastar el mismo tiempo con un hash dummy: así el
// atacante no distingue "email inexistente" de "contraseña incorrecta" por timing.
if (!$user) {
    ds_dummy_password_check();
    ds_login_record('cliente', $email, $ip, false);
    ds_json_error('Credenciales inválidas', 401);
}
if (!password_verify($password, $user['password_hash'])) {
    ds_login_record('cliente', $email, $ip, false);
    ds_json_error('Credenciales inválidas', 401);
}

// Mantenimiento del hash: si el algoritmo/coste por defecto de PHP subió desde que se
// guardó, re-hashear aprovechando que aquí la contraseña en claro todavía existe.
// password_changed_at NO se toca a propósito: marca cambios hechos por el usuario, y
// ds_session_check_password_change() (lib/Session.php) cierra las sesiones anteriores a
// esa marca — tocarla aquí expulsaría al usuario de la sesión que acaba de abrir.
// El try/catch evita que un fallo de escritura impida un login por lo demás válido.
if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
    try {
        $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), (int) $user['id']]);
    } catch (Throwable $e) {
        error_log('login.php rehash: ' . $e->getMessage());
    }
}

ds_login_record('cliente', $email, $ip, true);
ds_login_user((int) $user['id']);

ds_json_success(['id' => (int) $user['id'], 'nombre' => $user['nombre'], 'email' => $user['email']]);
