<?php
// Sesión de administrador. Clave: $_SESSION['admin_id'] (distinta de 'user_id' de clientes).
// Ambas sesiones pueden coexistir en el mismo navegador sin colisionar.

declare(strict_types=1);

// Caducidad de sesión (mismas constantes que el cliente; guardadas por si Session.php
// no se cargó en este endpoint admin).
if (!defined('DS_IDLE_TIMEOUT'))     define('DS_IDLE_TIMEOUT', 3600);       // 60 min
if (!defined('DS_ABSOLUTE_TIMEOUT')) define('DS_ABSOLUTE_TIMEOUT', 43200);  // 12 h

function ds_admin_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) { ds_admin_enforce_timeout(); return; }
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_secure', !empty($_SERVER['HTTPS']) ? '1' : '0');
    session_start();
    ds_admin_enforce_timeout();
}

// Cierra la sesión de ADMIN si superó el timeout de inactividad o el absoluto.
// Solo toca las claves de admin (no las de cliente).
function ds_admin_enforce_timeout(): void
{
    if (empty($_SESSION['admin_id'])) {
        return;
    }
    $now   = time();
    $last  = (int) ($_SESSION['admin_last_activity'] ?? $now);
    $login = (int) ($_SESSION['admin_login_time'] ?? $now);
    if (($now - $last) > DS_IDLE_TIMEOUT || ($now - $login) > DS_ABSOLUTE_TIMEOUT) {
        unset($_SESSION['admin_id'], $_SESSION['admin_csrf'],
              $_SESSION['admin_last_activity'], $_SESSION['admin_login_time']);
        return;
    }
    $_SESSION['admin_last_activity'] = $now;
}

function ds_current_admin_id(): ?int
{
    ds_admin_session_start();
    return isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;
}

function ds_require_admin(): int
{
    $id = ds_current_admin_id();
    if ($id === null) ds_json_error('No autenticado como administrador', 401);
    return $id;
}

function ds_login_admin(int $adminId): void
{
    ds_admin_session_start();
    session_regenerate_id(true);
    // Rotar el CSRF de admin al autenticarse (se renueva al cargar el panel vía me.php).
    unset($_SESSION['admin_csrf']);
    $_SESSION['admin_id'] = $adminId;
    $_SESSION['admin_login_time'] = time();
    $_SESSION['admin_last_activity'] = time();
}

function ds_logout_admin(): void
{
    ds_admin_session_start();
    unset($_SESSION['admin_id']);
    if (empty($_SESSION['user_id'])) session_destroy();
}

function ds_admin_csrf_token(): string
{
    ds_admin_session_start();
    if (empty($_SESSION['admin_csrf'])) {
        $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['admin_csrf'];
}

function ds_admin_csrf_check(?string $submitted): void
{
    ds_admin_session_start();
    $expected = $_SESSION['admin_csrf'] ?? '';
    if ($submitted === null || $expected === '' || !hash_equals($expected, $submitted)) {
        ds_json_error('Token de seguridad inválido', 403);
    }
}
