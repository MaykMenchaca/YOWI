<?php
// Throttling contra fuerza bruta y abuso. Usa la tabla login_attempts.
// Requiere que database.php y Response.php ya estén incluidos.

declare(strict_types=1);

// Login: por (tipo+email+IP) — objetivo directo.
const DS_LOGIN_MAX_FAILS  = 5;
const DS_LOGIN_WINDOW_MIN = 15;

// Login: límite global POR IP (frena password-spraying: 1 contraseña contra muchos
// emails desde una misma IP). Cuenta TODOS los fallos de esa IP, sin importar el email.
const DS_LOGIN_IP_MAX_FAILS = 20;

// Login: límite POR CUENTA independiente de la IP (frena botnets contra 1 cuenta).
const DS_LOGIN_ACCOUNT_MAX_FAILS = 15;

function ds_client_ip(): string
{
    // Por defecto, solo REMOTE_ADDR: X-Forwarded-For es falsificable y no se debe usar
    // para límites. OPT-IN: si el operador configura TRUSTED_IP_HEADER en env.php (p. ej.
    // 'CF-Connecting-IP' tras Cloudflare), se toma esa cabecera como IP real del cliente.
    static $trusted = null;
    if ($trusted === null) {
        $envPath = __DIR__ . '/../config/env.php';
        $env = file_exists($envPath) ? require $envPath : [];
        $trusted = trim((string) ($env['TRUSTED_IP_HEADER'] ?? '')); // '' = desactivado
    }
    if ($trusted !== '') {
        // 'CF-Connecting-IP' -> $_SERVER['HTTP_CF_CONNECTING_IP']
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $trusted));
        $val = trim((string) ($_SERVER[$key] ?? ''));
        // X-Forwarded-For puede traer lista "cliente, proxy1, ..."; tomar el primero.
        if ($val !== '' && strpos($val, ',') !== false) {
            $val = trim(explode(',', $val)[0]);
        }
        if ($val !== '' && filter_var($val, FILTER_VALIDATE_IP)) {
            return substr($val, 0, 45);
        }
    }
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

/**
 * Gasta el mismo tiempo que una verificación real cuando el usuario NO existe, para
 * evitar el oráculo de timing que revela emails válidos. Usa password_hash con
 * PASSWORD_DEFAULT (mismo algoritmo y coste con que se crearon los hashes reales en
 * ESTE servidor), así se auto-calibra y es portable entre versiones de PHP/hosts.
 * Llamar en la rama "usuario no encontrado".
 */
function ds_dummy_password_check(): void
{
    password_hash('dummy-password-to-equalize-timing', PASSWORD_DEFAULT);
}

/**
 * Corta la petición con 429 si hay demasiados intentos fallidos recientes.
 * Aplica TRES límites: (email+IP), (solo IP) y (solo cuenta). Llamar ANTES de verificar.
 * Las constantes de ventana/umbral son enteros del código (no input) → interpolación segura.
 */
function ds_login_throttle_check(string $tipo, string $email, string $ip): void
{
    $pdo = ds_get_pdo();

    // Lock de aplicación por (tipo+email): sin esto, N intentos de login concurrentes
    // contra la MISMA cuenta leen todos el mismo COUNT(*) de fallos por debajo del
    // umbral y pasan todos — con 50 en paralelo se prueban ~50 contraseñas por ventana
    // en vez de 5. El registro del intento (ds_login_record) ocurre en otra función,
    // después de verificar la contraseña (bcrypt, ~100ms); por eso el lock se adquiere
    // aquí y NO se libera explícitamente — MySQL lo libera solo cuando esta conexión
    // se cierra al terminar la petición, que es justo cuando ds_login_record ya corrió.
    // No serializa el login legítimo entre cuentas distintas, solo intentos concurrentes
    // contra la misma cuenta, que es exactamente el escenario de fuerza bruta.
    $pdo->prepare('SELECT GET_LOCK(?, 5)')->execute(['ds_login_' . md5($tipo . '|' . $email)]);

    $win = 'created_at > (NOW() - INTERVAL ' . DS_LOGIN_WINDOW_MIN . ' MINUTE)';

    // 1) Objetivo directo: mismo email + misma IP.
    $q1 = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE tipo = ? AND email = ? AND ip = ? AND exitoso = 0 AND $win");
    $q1->execute([$tipo, $email, $ip]);
    if ((int) $q1->fetchColumn() >= DS_LOGIN_MAX_FAILS) {
        ds_json_error('Demasiados intentos fallidos. Espera 15 minutos e inténtalo de nuevo.', 429);
    }

    // 2) Password-spraying: muchos fallos desde una misma IP (cualquier email).
    $q2 = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE tipo = ? AND ip = ? AND exitoso = 0 AND $win");
    $q2->execute([$tipo, $ip]);
    if ((int) $q2->fetchColumn() >= DS_LOGIN_IP_MAX_FAILS) {
        ds_json_error('Demasiados intentos desde tu red. Espera unos minutos e inténtalo de nuevo.', 429);
    }

    // 3) Ataque distribuido contra una cuenta: muchos fallos al mismo email (cualquier IP).
    $q3 = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE tipo = ? AND email = ? AND exitoso = 0 AND $win");
    $q3->execute([$tipo, $email]);
    if ((int) $q3->fetchColumn() >= DS_LOGIN_ACCOUNT_MAX_FAILS) {
        ds_json_error('Esta cuenta está temporalmente bloqueada por seguridad. Intenta más tarde.', 429);
    }
}

/**
 * Límite genérico por IP para acciones públicas sensibles distintas del login
 * (p. ej. registro). Reutiliza login_attempts con tipo = "accion:<nombre>".
 * Cuenta TODOS los registros (exitosos o no) de esa acción+IP en la ventana.
 * Llamar al inicio del endpoint; registra el intento y corta con 429 si excede.
 */
function ds_rate_limit_ip(string $accion, string $ip, int $max, int $ventanaMin): void
{
    $pdo  = ds_get_pdo();
    $tipo = 'cliente'; // la columna tipo es ENUM('cliente','admin'); se reutiliza 'cliente'.
    $key  = 'rl:' . $accion;
    $ventanaMin = max(1, $ventanaMin);

    // Lock de aplicación por (acción+IP): sin esto, "contar y luego insertar" es una
    // condición de carrera — N peticiones concurrentes leen todas el mismo COUNT(*) por
    // debajo del umbral y pasan todas. GET_LOCK serializa las peticiones que compiten
    // por la MISMA clave (no bloquea a otros usuarios/acciones). Si no se consigue el
    // lock en 5s (contención extrema), se deja pasar antes que colgar la petición —
    // el límite es una mitigación de abuso, no una garantía dura.
    $lockName = 'ds_rl_' . md5($key . '|' . $ip);
    $lockStmt = $pdo->prepare('SELECT GET_LOCK(?, 5)');
    $lockStmt->execute([$lockName]);
    $gotLock = (int) $lockStmt->fetchColumn() === 1;

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM login_attempts
         WHERE tipo = ? AND email = ? AND ip = ?
           AND created_at > (NOW() - INTERVAL {$ventanaMin} MINUTE)"
    );
    $stmt->execute([$tipo, $key, $ip]);
    if ((int) $stmt->fetchColumn() >= $max) {
        if ($gotLock) $pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([$lockName]);
        ds_json_error('Demasiadas solicitudes. Espera unos minutos e inténtalo de nuevo.', 429);
    }

    // Registrar este intento para contar el siguiente, TODAVÍA bajo el lock.
    $ins = $pdo->prepare('INSERT INTO login_attempts (tipo, email, ip, exitoso) VALUES (?, ?, ?, 1)');
    $ins->execute([$tipo, $key, $ip]);

    if ($gotLock) $pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([$lockName]);
}

/**
 * Registra el intento (éxito o fallo). Llamar tras verificar la contraseña.
 */
function ds_login_record(string $tipo, string $email, string $ip, bool $exitoso): void
{
    $pdo = ds_get_pdo();
    $stmt = $pdo->prepare('INSERT INTO login_attempts (tipo, email, ip, exitoso) VALUES (?, ?, ?, ?)');
    $stmt->execute([$tipo, $email, $ip, $exitoso ? 1 : 0]);

    // Limpieza oportunista de intentos viejos (>1 día) ~5% de las veces.
    if (random_int(1, 20) === 1) {
        $pdo->exec('DELETE FROM login_attempts WHERE created_at < (NOW() - INTERVAL 1 DAY)');
    }
}
