<?php
// Conexión PDO única, reutilizada por todos los endpoints.
// Lee credenciales de env.php (gitignored). Ver env.example.php para la plantilla.

declare(strict_types=1);

// Endurecimiento de errores en producción (display_errors off + logging). Es lo primero
// que corre en cada endpoint porque database.php se incluye al inicio de todos ellos.
require_once __DIR__ . '/../lib/Bootstrap.php';

// Carga env.php una sola vez (cacheada). Extraído de ds_get_pdo() para que otros
// consumidores de secretos (p. ej. lib/Crypto.php, la clave de cifrado del 2FA) tengan una
// forma soportada de leerlo sin volver a parsear el archivo por su cuenta.
function ds_get_env(): array
{
    static $env = null;
    if ($env !== null) {
        return $env;
    }
    $envPath = __DIR__ . '/env.php';
    if (!file_exists($envPath)) {
        throw new RuntimeException('Falta site/api/config/env.php — copiar desde env.example.php y completar credenciales.');
    }
    $env = require $envPath;
    return $env;
}

function ds_get_pdo(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $env = ds_get_env();

    $dsn = sprintf(
        'mysql:host=%s%s;dbname=%s;charset=%s',
        $env['DB_HOST'],
        isset($env['DB_PORT']) ? ';port=' . $env['DB_PORT'] : '',
        $env['DB_NAME'],
        $env['DB_CHARSET'] ?? 'utf8mb4'
    );

    $pdo = new PDO($dsn, $env['DB_USER'], $env['DB_PASS'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}
