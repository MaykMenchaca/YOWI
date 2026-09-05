<?php
declare(strict_types=1);

// "Iniciar sesión con Google" — OAuth 2.0, código de autorización, del lado del
// servidor. Sin el SDK de Google en el navegador a propósito: así no hace falta abrir
// el CSP del sitio (script-src 'self') a un dominio externo — todo el intercambio con
// Google pasa por aquí, servidor a servidor.

function ds_google_config(): array
{
    static $cfg = null;
    if ($cfg !== null) {
        return $cfg;
    }
    $env = ds_get_env();
    $cfg = [
        'client_id'     => (string) ($env['GOOGLE_CLIENT_ID'] ?? ''),
        'client_secret' => (string) ($env['GOOGLE_CLIENT_SECRET'] ?? ''),
    ];
    return $cfg;
}

// Requiere las dos claves de Google Y una APP_URL configurada (Google exige un
// redirect_uri absoluto con dominio, no una ruta relativa) — si falta cualquiera de
// las tres, "Continuar con Google" se apaga solo en vez de mandar una URL rota a Google.
function ds_google_enabled(): bool
{
    $cfg = ds_google_config();
    return $cfg['client_id'] !== '' && $cfg['client_secret'] !== '' && ds_app_url() !== '';
}

function ds_google_redirect_uri(): string
{
    return rtrim(ds_app_url(), '/') . '/api/auth/google-callback.php';
}

// POST de formulario sin depender de curl (no todo hosting compartido lo garantiza; el
// resto del proyecto tampoco lo usa — ds_send_mail() usa mail() nativo de PHP).
// Devuelve el JSON decodificado, o null si algo falló.
function ds_http_post_form(string $url, array $fields): ?array
{
    $opts = ['http' => [
        'method'        => 'POST',
        'header'        => "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json\r\n",
        'content'       => http_build_query($fields),
        'timeout'       => 10,
        'ignore_errors' => true, // para poder leer el body de error de Google también
    ]];
    $ctx = stream_context_create($opts);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) {
        return null;
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}

// Decodifica el payload de un id_token (JWT) SIN verificar la firma. Es seguro aquí
// porque el token nunca pasa por el navegador del cliente: llega directo en la
// respuesta de una petición HTTPS servidor-a-servidor a oauth2.googleapis.com — esa
// conexión TLS con Google YA es la garantía de autenticidad que la firma daría. Lo que
// SÍ hay que validar del payload antes de confiar en él lo hace google-callback.php
// (aud, exp, email_verified, email, sub).
function ds_decode_id_token(string $idToken): ?array
{
    $parts = explode('.', $idToken);
    if (count($parts) !== 3) {
        return null;
    }
    $payloadRaw = base64_decode(strtr($parts[1], '-_', '+/'), true);
    if ($payloadRaw === false) {
        return null;
    }
    $payload = json_decode($payloadRaw, true);
    return is_array($payload) ? $payload : null;
}
