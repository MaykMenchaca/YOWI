<?php
declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../lib/Response.php';
require __DIR__ . '/../lib/Session.php';
require __DIR__ . '/../lib/RateLimit.php';
require __DIR__ . '/../lib/GoogleAuth.php';

// GET /api/auth/google-start.php — punto de entrada del botón "Continuar con Google".
// Es una navegación normal (<a href>), no un fetch: los errores se comunican
// redirigiendo a una página real con un flag en la URL, nunca con un JSON crudo.

function ds_google_redirect(string $to): void
{
    header('Location: ' . $to, true, 302);
    exit;
}

if (!ds_google_enabled()) {
    // Sin credenciales (o sin APP_URL) configuradas: apagado seguro, no error fatal.
    ds_google_redirect('/login.html?google_error=1');
}

// Anti-abuso ligero: no hay CSRF aquí (es una navegación GET, no un formulario), pero
// limitar por IP evita que alguien reintente este endpoint en bucle. Mismo helper que
// ya usa register.php.
ds_rate_limit_ip('google-start', ds_client_ip(), 30, 15);

ds_session_start();
$state = bin2hex(random_bytes(32));
$_SESSION['google_oauth_state'] = $state;

$params = [
    'client_id'     => ds_google_config()['client_id'],
    'redirect_uri'  => ds_google_redirect_uri(),
    'response_type' => 'code',
    'scope'         => 'openid email profile',
    'state'         => $state,
    // Sin esto, alguien con varias cuentas de Google en el navegador siempre reentra
    // a la misma sin poder elegir otra.
    'prompt'        => 'select_account',
];

ds_google_redirect('https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params));
