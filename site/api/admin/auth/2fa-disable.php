<?php
declare(strict_types=1);

require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../lib/Response.php';
require __DIR__ . '/../../lib/AdminSession.php';
require __DIR__ . '/../../lib/Totp.php';
require __DIR__ . '/../../lib/Crypto.php';
require __DIR__ . '/../../lib/RateLimit.php';
require __DIR__ . '/../../lib/Recovery.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') ds_json_error('Método no permitido', 405);

$adminId = ds_require_admin();

$body = ds_read_json_body();
ds_admin_csrf_check($body['csrf_token'] ?? null);

// Sin límite, una sesión secuestrada podía probar códigos TOTP indefinidamente (3 de
// 10^6 posibles válidos en cada ventana de ~90s) hasta desactivar el 2FA por fuerza
// bruta. 10 intentos / 15 min es holgado para un uso legítimo (un solo código).
ds_rate_limit_ip('2fa', ds_client_ip(), 10, 15);

// Para desactivar exige un código TOTP válido actual O un código de recuperación de un
// solo uso (evita que un atacante con la sesión abierta lo quite sin tener el segundo
// factor). Sin el código de recuperación como alternativa, un admin que pierde su
// autenticador podía entrar con un código de recuperación (login.php sí los acepta) pero
// quedaba sin forma de re-enrolar: 2fa-setup.php exige 2FA ya desactivado, y esta era la
// única puerta para desactivarlo — un candado sin llave de repuesto.
$factor = (string) ($body['code'] ?? '');
$digits = preg_replace('/\D+/', '', $factor);

$pdo = ds_get_pdo();
$stmt = $pdo->prepare('SELECT totp_secret, totp_enabled FROM admins WHERE id = ?');
$stmt->execute([$adminId]);
$admin = $stmt->fetch();
if (!$admin || (int) $admin['totp_enabled'] !== 1) {
    ds_json_error('El 2FA no está activo', 400);
}

$secretPlain = $admin['totp_secret'] !== null ? ds_decrypt((string) $admin['totp_secret']) : null;
$totpOk = $digits !== '' && $secretPlain !== null && ds_totp_verify($secretPlain, $digits);
$recoveryOk = !$totpOk && ds_consume_recovery_code($pdo, $adminId, $factor);
if (!$totpOk && !$recoveryOk) {
    ds_json_error('Código de verificación inválido', 401);
}

$upd = $pdo->prepare('UPDATE admins SET totp_enabled = 0, totp_secret = NULL WHERE id = ?');
$upd->execute([$adminId]);

ds_json_success(['enabled' => false]);
