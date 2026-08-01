<?php
// Conexión PDO única, reutilizada por todos los endpoints.
// Lee credenciales de env.php (gitignored). Ver env.example.php para la plantilla.

declare(strict_types=1);

// Endurecimiento de errores en producción (display_errors off + logging). Es lo primero
// que corre en cada endpoint porque database.php se incluye al inicio de todos ellos.
require_once __DIR__ . '/../lib/Bootstrap.php';

function ds_get_pdo(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $envPath = __DIR__ . '/env.php';
    if (!file_exists($envPath)) {
        throw new RuntimeException('Falta site/api/config/env.php — copiar desde env.example.php y completar credenciales.');
    }
    $env = require $envPath;

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
