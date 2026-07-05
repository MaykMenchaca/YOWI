<?php
// Throttling de logins contra fuerza bruta. Usa la tabla login_attempts.
// Bloquea tras DS_LOGIN_MAX_FAILS fallos en DS_LOGIN_WINDOW_MIN minutos (por tipo+email+IP).
// Requiere que database.php y Response.php ya estén incluidos.

declare(strict_types=1);

const DS_LOGIN_MAX_FAILS  = 5;
const DS_LOGIN_WINDOW_MIN = 15;

function ds_client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

/**
 * Corta la petición con 429 si ya hay demasiados intentos fallidos recientes.
 * Llamar ANTES de verificar la contraseña.
 */
function ds_login_throttle_check(string $tipo, string $email, string $ip): void
{
    $pdo = ds_get_pdo();
    // DS_LOGIN_WINDOW_MIN es una constante entera del código (no input), interpolarla es seguro.
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM login_attempts
         WHERE tipo = ? AND email = ? AND ip = ? AND exitoso = 0
           AND created_at > (NOW() - INTERVAL ' . DS_LOGIN_WINDOW_MIN . ' MINUTE)'
    );
    $stmt->execute([$tipo, $email, $ip]);
    if ((int) $stmt->fetchColumn() >= DS_LOGIN_MAX_FAILS) {
        ds_json_error('Demasiados intentos fallidos. Espera 15 minutos e inténtalo de nuevo.', 429);
    }
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
