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

// Carga los datos del admin actual UNA sola vez por petición (caché estática — evita que
// ds_require_rol() de cada endpoint repita la consulta que ya hizo ds_require_admin()).
// Devuelve null si no hay sesión, si el admin fue borrado, si está desactivado
// (admins.activo = 0 — así queda efectiva la baja de un empleado: en su siguiente
// petición queda fuera, aunque tuviera la sesión abierta), o si cambió su contraseña
// DESPUÉS de este login (cierra la sesión — réplica literal de
// ds_session_check_password_change() en lib/Session.php, mismo motivo de usar
// UNIX_TIMESTAMP() en vez de strtotime(): evita el desfase de zona horaria entre PHP y
// MySQL documentado ahí). En cualquiera de esos tres casos, además cierra la sesión admin
// (unset de las 4 claves, nunca session_destroy — la sesión de cliente puede coexistir).
function ds_admin_actual(): ?array
{
    static $cache = false; // false = "aún no se calculó"; null = "no hay admin válido"
    if ($cache !== false) return $cache;

    ds_admin_session_start();
    if (empty($_SESSION['admin_id']) || !function_exists('ds_get_pdo')) {
        return $cache = null;
    }

    $cerrarSesion = function (): void {
        unset($_SESSION['admin_id'], $_SESSION['admin_csrf'],
              $_SESSION['admin_last_activity'], $_SESSION['admin_login_time']);
    };

    try {
        $stmt = ds_get_pdo()->prepare(
            'SELECT id, nombre, email, rol, activo, totp_enabled,
                    UNIX_TIMESTAMP(password_changed_at) AS pw_ts
             FROM admins WHERE id = ?'
        );
        $stmt->execute([(int) $_SESSION['admin_id']]);
        $row = $stmt->fetch();
    } catch (Throwable $e) {
        error_log('ds_admin_actual: ' . $e->getMessage());
        return $cache = null;
    }

    if (!$row) {
        $cerrarSesion(); // el admin ya no existe (borrado)
        return $cache = null;
    }

    $login = (int) ($_SESSION['admin_login_time'] ?? 0);
    $pwTs = $row['pw_ts'];
    $passwordCambiadaDespues = $pwTs !== null && $pwTs !== false && (int) $pwTs > $login;

    if ((int) $row['activo'] !== 1 || $passwordCambiadaDespues) {
        $cerrarSesion();
        return $cache = null;
    }

    return $cache = [
        'id'           => (int) $row['id'],
        'nombre'       => (string) $row['nombre'],
        'email'        => (string) $row['email'],
        'rol'          => (string) $row['rol'],
        'totp_enabled' => (int) $row['totp_enabled'] === 1,
    ];
}

function ds_current_admin_id(): ?int
{
    $admin = ds_admin_actual();
    return $admin['id'] ?? null;
}

// Único guardián de admin del proyecto: exige sesión activa y, salvo que se pida
// explícitamente lo contrario, exige 2FA activo. Antes el enforcement de 2FA vivía solo
// dentro de ds_admin_csrf_check() (solo se llamaba en POST), así que cualquier GET
// —incluido el volcado completo de la BD en backup/export.php— quedaba sin protección
// para un admin con totp_enabled=0. $permitirSinEnrolar=true es SOLO para los endpoints
// que un admin necesita antes de tener 2FA activo (enrolarlo, consultar su estado, o
// regenerar los códigos de recuperación, que ya exige un TOTP válido por su cuenta).
function ds_require_admin(bool $permitirSinEnrolar = false): int
{
    $admin = ds_admin_actual();
    if ($admin === null) ds_json_error('No autenticado como administrador', 401);
    if (!$permitirSinEnrolar && !$admin['totp_enabled']) {
        ds_json_error('Debes activar el 2FA antes de operar el panel.', 403);
    }
    return $admin['id'];
}

// Jerarquía de roles: dueno > operador > lectura. Un rol que no esté en la whitelist
// (dato corrupto, típo, o un valor futuro que el código aún no reconoce) devuelve 0 — el
// efecto es bloqueo, nunca acceso.
const DS_ROL_LECTURA  = 'lectura';
const DS_ROL_OPERADOR = 'operador';
const DS_ROL_DUENO    = 'dueno';

function ds_rol_nivel(string $rol): int
{
    switch ($rol) {
        case DS_ROL_DUENO:    return 3;
        case DS_ROL_OPERADOR: return 2;
        case DS_ROL_LECTURA:  return 1;
        default:              return 0;
    }
}

// Guardián por endpoint que además exige un rol mínimo. Exige primero sesión + 2FA (igual
// que ds_require_admin) y luego compara el nivel del rol del admin contra $rolMinimo
// (una de las constantes DS_ROL_*). Reusa la caché de ds_admin_actual(), así que no repite
// la consulta a BD que ya hizo ds_require_admin().
function ds_require_rol(string $rolMinimo, bool $permitirSinEnrolar = false): int
{
    $id = ds_require_admin($permitirSinEnrolar);
    $admin = ds_admin_actual();
    if (ds_rol_nivel($admin['rol']) < ds_rol_nivel($rolMinimo)) {
        ds_json_error('Tu cuenta no tiene permiso para esta acción.', 403);
    }
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
    // Auditoría automática: toda acción de escritura admin pasa por aquí. Se registra
    // la ruta como "acción". No audita el login (aún no hay admin_id en sesión).
    // El enforcement de 2FA ya NO vive aquí (solo corría en POST) — ahora es
    // ds_require_admin() quien lo exige, en cada endpoint, GET o POST.
    $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    $accion = trim(preg_replace('#^.*/admin/#', '', $script), '/');
    $accion = $accion !== '' ? $accion : basename($script);
    ds_admin_log($accion, null);
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
