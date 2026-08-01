<?php
// Bootstrap de seguridad para producción. Se incluye al principio de config/database.php
// (que a su vez es lo primero que requiere CADA endpoint), así aplica en toda la API.
//
// Objetivo: que NUNCA se filtren errores/warnings de PHP al cliente (revelan rutas,
// versiones, fragmentos de SQL). Los errores se REGISTRAN en un log del servidor, no
// se muestran. Se puede forzar el modo debug SOLO en local con DS_DEBUG=1 en env/entorno.

declare(strict_types=1);

if (!defined('DS_BOOTSTRAPPED')) {
    define('DS_BOOTSTRAPPED', true);

    $debug = getenv('DS_DEBUG') === '1'
        || (isset($_SERVER['DS_DEBUG']) && $_SERVER['DS_DEBUG'] === '1');

    if ($debug) {
        ini_set('display_errors', '1');
        error_reporting(E_ALL);
    } else {
        // Producción: nada se muestra al cliente; todo se loguea.
        ini_set('display_errors', '0');
        ini_set('display_startup_errors', '0');
        error_reporting(E_ALL);
    }

    ini_set('log_errors', '1');

    // Si hay una ruta de log configurada y escribible, usarla; si no, se deja el
    // error_log por defecto del servidor (Hostinger lo enruta a su propio log).
    $logPath = getenv('DS_ERROR_LOG') ?: ($_SERVER['DS_ERROR_LOG'] ?? '');
    if (is_string($logPath) && $logPath !== '') {
        ini_set('error_log', $logPath);
    }

    // Convertir warnings/notices en excepciones capturables NO es deseable aquí (podría
    // romper flujos que toleran warnings). En su lugar, un manejador que los registra
    // sin imprimirlos. Respeta el operador @.
    set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
        if (!(error_reporting() & $severity)) {
            return false; // silenciado con @ o fuera del nivel: comportamiento normal.
        }
        error_log(sprintf('PHP %d: %s in %s:%d', $severity, $message, $file, $line));
        // Devolver true = "manejado": no se imprime al cliente.
        return true;
    });
}
