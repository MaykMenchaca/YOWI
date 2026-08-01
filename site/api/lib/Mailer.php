<?php
// Capa de envío de correo + helpers de tokens (verificación de email y reset de clave).
// El envío está DESACTIVADO por defecto: solo se activa configurando MAIL_TRANSPORT en
// env.php. Así las funciones se pueden desplegar sin romper nada hasta tener correo.
//
// Transportes (env 'MAIL_TRANSPORT'):
//   'none' (por defecto) -> correo desactivado. ds_email_enabled() = false.
//   'log'  -> escribe el correo en el error_log (útil para pruebas, no envía nada).
//   'mail' -> usa la función mail() de PHP (Hostinger).
// (SMTP autenticado requeriría una librería tipo PHPMailer; se puede añadir después.)

declare(strict_types=1);

function ds_mail_config(): array
{
    static $cfg = null;
    if ($cfg !== null) return $cfg;
    $envPath = __DIR__ . '/../config/env.php';
    $env = file_exists($envPath) ? require $envPath : [];
    $cfg = [
        'transport' => $env['MAIL_TRANSPORT'] ?? 'none',
        'from'      => $env['MAIL_FROM'] ?? 'no-reply@localhost',
        'from_name' => $env['MAIL_FROM_NAME'] ?? 'Distribuidor de Suplementos',
        'app_url'   => rtrim((string) ($env['APP_URL'] ?? ''), '/'),
    ];
    return $cfg;
}

/** ¿Está el correo configurado (transporte distinto de 'none')? */
function ds_email_enabled(): bool
{
    return ds_mail_config()['transport'] !== 'none';
}

/** URL pública de la app para armar enlaces. Vacía si no está configurada. */
function ds_app_url(): string
{
    return ds_mail_config()['app_url'];
}

/** Envía un correo (texto plano). Devuelve true si el transporte lo aceptó. */
function ds_send_mail(string $to, string $subject, string $body): bool
{
    $cfg = ds_mail_config();
    switch ($cfg['transport']) {
        case 'log':
            error_log("MAIL[log] to={$to} subject={$subject}\n{$body}");
            return true;
        case 'mail':
            $headers = 'From: ' . $cfg['from_name'] . ' <' . $cfg['from'] . ">\r\n"
                     . "Content-Type: text/plain; charset=utf-8\r\n";
            return @mail($to, $subject, $body, $headers);
        case 'none':
        default:
            return false;
    }
}

// ── Tokens (verificación de email / reset de contraseña) ──────────────────────

/**
 * Genera un token de un solo uso: devuelve [plano, hash]. El PLANO va en el enlace del
 * correo; en la BD se guarda SOLO el HASH (SHA-256), nunca el plano.
 */
function ds_make_token(): array
{
    $plain = bin2hex(random_bytes(32));
    return [$plain, hash('sha256', $plain)];
}

/** Hash de un token recibido, para buscarlo en la BD. */
function ds_hash_token(string $plain): string
{
    return hash('sha256', $plain);
}
