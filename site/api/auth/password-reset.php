<?php
declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../lib/Response.php';
require __DIR__ . '/../lib/Session.php';
require __DIR__ . '/../lib/Csrf.php';
require __DIR__ . '/../lib/Validate.php';
require __DIR__ . '/../lib/Mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ds_json_error('Método no permitido', 405);
}

$body = ds_read_json_body();
ds_csrf_check($body['csrf_token'] ?? null);

$token           = (string) ($body['token'] ?? '');
$password        = (string) ($body['password'] ?? '');
$confirmPassword = (string) ($body['confirm_password'] ?? '');

if ($token === '' || !ctype_xdigit($token)) {
    ds_json_error('Enlace inválido o expirado', 400);
}
if (strlen($password) < 8) {
    ds_json_error('La contraseña debe tener al menos 8 caracteres', 400);
}
if ($password !== $confirmPassword) {
    ds_json_error('Las contraseñas no coinciden', 400);
}

$pdo  = ds_get_pdo();
$hash = ds_hash_token($token);

$stmt = $pdo->prepare(
    'SELECT id, user_id FROM password_resets
     WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()'
);
$stmt->execute([$hash]);
$row = $stmt->fetch();

if (!$row) {
    ds_json_error('El enlace es inválido o ya expiró. Solicita uno nuevo.', 400);
}

$userId    = (int) $row['user_id'];
$newHash   = password_hash($password, PASSWORD_DEFAULT);

$pdo->beginTransaction();
try {
    $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$newHash, $userId]);
    // Marcar este token como usado e invalidar cualquier otro del usuario.
    $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ?')->execute([(int) $row['id']]);
    $pdo->prepare('DELETE FROM password_resets WHERE user_id = ? AND used_at IS NULL')->execute([$userId]);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('password-reset.php: ' . $e->getMessage());
    ds_json_error('No se pudo restablecer la contraseña', 500);
}

ds_json_success(['message' => 'Tu contraseña se actualizó. Ya puedes iniciar sesión.']);
