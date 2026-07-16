<?php
// Arranque de sesión seguro + helpers de autenticación actual.

declare(strict_types=1);

function ds_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_strict_mode', '1');

    // Modo "frontend en Vercel + API aparte": el frontend vive en otro dominio,
    // así que la cookie de sesión es cross-site. Para que el navegador la envíe
    // hace falta SameSite=None + Secure (obligatorio). Se activa poniendo
    // `SetEnv DS_CROSS_SITE 1` en el .htaccess del backend (Hostinger).
    // NOTA: los navegadores modernos restringen cookies de terceros; para
    // login/cuenta/admin lo más robusto es servir el frontend en el mismo
    // dominio del backend.
    $crossSite = getenv('DS_CROSS_SITE') === '1'
        || (isset($_SERVER['DS_CROSS_SITE']) && $_SERVER['DS_CROSS_SITE'] === '1');

    if ($crossSite) {
        ini_set('session.cookie_samesite', 'None');
        ini_set('session.cookie_secure', '1');
    } else {
        ini_set('session.cookie_samesite', 'Lax');
        // Solo enviar la cookie por HTTPS cuando esté disponible (Hostinger sirve HTTPS).
        ini_set('session.cookie_secure', !empty($_SERVER['HTTPS']) ? '1' : '0');
    }
    session_start();
}

function ds_current_user_id(): ?int
{
    ds_session_start();
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

function ds_require_login(): int
{
    $userId = ds_current_user_id();
    if ($userId === null) {
        ds_json_error('No autenticado', 401);
    }
    return $userId;
}

function ds_login_user(int $userId): void
{
    ds_session_start();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
}

function ds_logout_user(): void
{
    ds_session_start();
    // Cerrar solo la sesión de cliente. Si hay un admin logueado en el mismo
    // navegador, su sesión debe sobrevivir (simétrico con ds_logout_admin).
    unset($_SESSION['user_id'], $_SESSION['csrf_token']);
    if (empty($_SESSION['admin_id'])) {
        session_destroy();
    }
}
