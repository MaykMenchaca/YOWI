<?php
// Sesión de administrador. Clave: $_SESSION['admin_id'] (distinta de 'user_id' de clientes).
// Ambas sesiones pueden coexistir en el mismo navegador sin colisionar.

declare(strict_types=1);

function ds_admin_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_secure', !empty($_SERVER['HTTPS']) ? '1' : '0');
    session_start();
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
