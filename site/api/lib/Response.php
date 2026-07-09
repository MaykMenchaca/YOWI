<?php
// Helpers de respuesta JSON uniforme para todos los endpoints.
// Contrato: { "ok": true, "data": ... } o { "ok": false, "error": "mensaje" }

declare(strict_types=1);

function ds_json_success(array $data = [], int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

function ds_json_error(string $message, int $status = 400): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function ds_read_json_body(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

// Red de seguridad: cualquier excepción no capturada (p. ej. BD caída) responde JSON
// uniforme en vez de filtrar un stack trace PHP con rutas del servidor.
set_exception_handler(function (Throwable $e): void {
    error_log('API uncaught: ' . $e->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['ok' => false, 'error' => 'Error del servidor'], JSON_UNESCAPED_UNICODE);
    exit;
});
