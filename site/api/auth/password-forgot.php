<?php
declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../lib/Response.php';
require __DIR__ . '/../lib/Session.php';
require __DIR__ . '/../lib/Csrf.php';
require __DIR__ . '/../lib/Validate.php';
require __DIR__ . '/../lib/RateLimit.php';
require __DIR__ . '/../lib/Mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ds_json_error('Método no permitido', 405);
}

$body = ds_read_json_body();
ds_csrf_check($body['csrf_token'] ?? null);

// Anti-abuso: máx. 5 solicitudes por IP cada 30 min.
ds_rate_limit_ip('pwforgot', ds_client_ip(), 5, 30);

$email = ds_validate_email((string) ($body['email'] ?? ''));

// Respuesta SIEMPRE genérica (no revela si el correo existe → anti-enumeración).
$generic = ['message' => 'Si el correo está registrado, te enviamos instrucciones para restablecer tu contraseña.'];

if ($email === null) {
    ds_json_success($generic);
}

$pdo = ds_get_pdo();
$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch();

// Si el usuario existe pero el correo NO está configurado (MAIL_TRANSPORT=none, el
// valor por defecto), el usuario ve el mismo mensaje genérico de siempre (intencional,
// anti-enumeración) pero en realidad se queda bloqueado sin ninguna forma de recuperar
// su cuenta. Dejarlo en el log para que el operador lo note en producción.
if ($user && !ds_email_enabled()) {
    error_log('password-forgot.php: MAIL_TRANSPORT=none, no se pudo enviar el correo de reset para user_id=' . (int) $user['id']);
}

// Solo si el usuario existe y el correo está configurado se genera y envía el token.
if ($user && ds_email_enabled()) {
    // Invalidar tokens previos del usuario.
    $pdo->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([(int) $user['id']]);

    [$plain, $hash] = ds_make_token();
    $ins = $pdo->prepare(
        'INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, (NOW() + INTERVAL 60 MINUTE))'
    );
    $ins->execute([(int) $user['id'], $hash]);

    $base = ds_app_url() ?: '';
    $link = $base . '/restablecer.html?token=' . $plain;
    $texto = "Recibimos una solicitud para restablecer tu contraseña.\n\n"
           . "Abre este enlace (válido 60 minutos):\n{$link}\n\n"
           . "Si no fuiste tú, ignora este mensaje.";
    ds_send_mail($email, 'Restablece tu contraseña — Distribuidor de Suplementos', $texto);
}

ds_json_success($generic);
