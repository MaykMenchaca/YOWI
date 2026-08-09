<?php
// Arranque de sesión seguro + helpers de autenticación actual.

declare(strict_types=1);

// Caducidad de sesión. Idle = tras inactividad; absoluta = desde el login, pase lo que pase.
if (!defined('DS_IDLE_TIMEOUT'))     define('DS_IDLE_TIMEOUT', 3600);       // 60 min
if (!defined('DS_ABSOLUTE_TIMEOUT')) define('DS_ABSOLUTE_TIMEOUT', 43200);  // 12 h

function ds_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        ds_session_enforce_timeout();
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
        // $_SERVER['HTTPS'] solo lo pone el propio servidor web con TLS terminado ahí
        // mismo; detrás de un proxy/CDN con TLS terminado ANTES (Cloudflare, etc. — los
        // .htaccess de este proyecto ya asumen esto al mirar X-Forwarded-Proto para el
        // redirect a HTTPS) puede llegar vacío aunque el usuario sí esté en HTTPS, y la
        // cookie de sesión se emitía sin Secure por error.
        $httpsDirecto = !empty($_SERVER['HTTPS']);
        $httpsPorProxy = isset($_SERVER['HTTP_X_FORWARDED_PROTO'])
            && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https';
        ini_set('session.cookie_secure', ($httpsDirecto || $httpsPorProxy) ? '1' : '0');
    }
    session_start();
    ds_session_enforce_timeout();
}

// Cierra la sesión de CLIENTE si superó el timeout de inactividad o el absoluto.
// Solo toca las claves del cliente (no las de admin, que coexisten).
function ds_session_enforce_timeout(): void
{
    if (empty($_SESSION['user_id'])) {
        return;
    }
    $now   = time();
    $last  = (int) ($_SESSION['user_last_activity'] ?? $now);
    $login = (int) ($_SESSION['user_login_time'] ?? $now);
    if (($now - $last) > DS_IDLE_TIMEOUT || ($now - $login) > DS_ABSOLUTE_TIMEOUT) {
        unset($_SESSION['user_id'], $_SESSION['csrf_token'],
              $_SESSION['user_last_activity'], $_SESSION['user_login_time']);
        return;
    }
    $_SESSION['user_last_activity'] = $now;
}

// Invalida la sesión de cliente si la contraseña cambió (p. ej. tras un reset)
// DESPUÉS del momento del login actual. Un lookup por PK por request autenticado.
function ds_session_check_password_change(): void
{
    if (empty($_SESSION['user_id']) || !function_exists('ds_get_pdo')) return;
    $login = (int) ($_SESSION['user_login_time'] ?? 0);
    try {
        // UNIX_TIMESTAMP() convierte DENTRO de MySQL, con la zona horaria de sesión de
        // MySQL — no con strtotime() de PHP. Antes se comparaba un string tipo
        // "2026-08-09 14:03:21" (interpretado con la zona horaria de PHP) contra
        // time() (siempre UTC); si PHP y MySQL no comparten zona horaria (típico en
        // hosting compartido: MySQL en UTC, PHP en la zona local), la comparación podía
        // desfasarse varias horas — una sesión robada podía sobrevivir horas después de
        // que la víctima cambiara su contraseña, pensando que ya la había invalidado.
        $stmt = ds_get_pdo()->prepare('SELECT UNIX_TIMESTAMP(password_changed_at) FROM users WHERE id = ?');
        $stmt->execute([(int) $_SESSION['user_id']]);
        $changedTs = $stmt->fetchColumn();
        if ($changedTs !== null && $changedTs !== false && (int) $changedTs > $login) {
            unset($_SESSION['user_id'], $_SESSION['csrf_token'],
                  $_SESSION['user_last_activity'], $_SESSION['user_login_time']);
        }
    } catch (Throwable $e) {
        error_log('pw-change check: ' . $e->getMessage());
    }
}

function ds_current_user_id(): ?int
{
    ds_session_start();
    ds_session_check_password_change();
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
    // Rotar el token CSRF al autenticarse: el cliente obtiene uno nuevo al recargar
    // (me.php). Evita que un token pre-login quede válido tras iniciar sesión.
    unset($_SESSION['csrf_token']);
    $_SESSION['user_id'] = $userId;
    $_SESSION['user_login_time'] = time();
    $_SESSION['user_last_activity'] = time();
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
