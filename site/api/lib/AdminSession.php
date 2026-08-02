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
    ini_set('session.use_strict_mode', '1');
    // Simétrico con Session.php (cliente): en modo cross-site (frontend en otro dominio)
    // la cookie admin necesita SameSite=None + Secure para viajar; si no, Lax.
    $crossSite = getenv('DS_CROSS_SITE') === '1'
        || (isset($_SERVER['DS_CROSS_SITE']) && $_SERVER['DS_CROSS_SITE'] === '1');
    if ($crossSite) {
        ini_set('session.cookie_samesite', 'None');
        ini_set('session.cookie_secure', '1');
    } else {
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.cookie_secure', !empty($_SERVER['HTTPS']) ? '1' : '0');
    }
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

// Endpoints exentos del enforcement de 2FA (enrolamiento / auth). Se comparan por
// basename de SCRIPT_NAME. login/logout no tienen admin_id útil en este punto; setup/
// activate/recovery deben poder usarse mientras totp_enabled sigue en 0.
const DS_2FA_EXEMPT = [
    'login.php', 'logout.php',
    '2fa-setup.php', '2fa-activate.php', '2fa-recovery.php',
];

// Enforza en el servidor que el admin tenga 2FA activo antes de operar el panel.
// El "2FA obligatorio" vivía solo en el front (needs_2fa), esquivable llamando la API.
function ds_admin_require_2fa_enrolled(): void
{
    $base = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if (in_array($base, DS_2FA_EXEMPT, true)) return;
    $adminId = $_SESSION['admin_id'] ?? null;
    if (empty($adminId) || !function_exists('ds_get_pdo')) return; // login flow lo maneja aparte
    $stmt = ds_get_pdo()->prepare('SELECT totp_enabled FROM admins WHERE id = ?');
    $stmt->execute([(int) $adminId]);
    if ((int) $stmt->fetchColumn() !== 1) {
        ds_json_error('Debes activar el 2FA antes de operar el panel.', 403);
    }
}

function ds_admin_csrf_check(?string $submitted): void
{
    ds_admin_session_start();
    $expected = $_SESSION['admin_csrf'] ?? '';
    if ($submitted === null || $expected === '' || !hash_equals($expected, $submitted)) {
        ds_json_error('Token de seguridad inválido', 403);
    }
    // Auditoría automática: toda acción de escritura admin pasa por aquí. Se registra
    // la ruta como "acción". No audita el login (aún no hay admin_id en sesión).
    $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    $accion = trim(preg_replace('#^.*/admin/#', '', $script), '/');
    $accion = $accion !== '' ? $accion : basename($script);
    ds_admin_log($accion, null);
    // 2FA obligatorio en servidor: bloquea escrituras si el admin no ha activado 2FA
    // (salvo los endpoints de enrolamiento/auth en DS_2FA_EXEMPT).
    ds_admin_require_2fa_enrolled();
}

/**
 * Registra una acción del admin en admin_audit_log. No hace nada si no hay admin en
 * sesión. Es defensivo (no rompe la petición si la tabla no existe todavía).
 */
function ds_admin_log(string $accion, ?string $detalle = null): void
{
    $adminId = $_SESSION['admin_id'] ?? null;
    if (empty($adminId) || !function_exists('ds_get_pdo')) {
        return;
    }
    try {
        $ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
        $stmt = ds_get_pdo()->prepare(
            'INSERT INTO admin_audit_log (admin_id, accion, detalle, ip) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([(int) $adminId, mb_substr($accion, 0, 80), $detalle !== null ? mb_substr($detalle, 0, 255) : null, $ip]);
    } catch (Throwable $e) {
        error_log('ds_admin_log: ' . $e->getMessage());
    }
}
