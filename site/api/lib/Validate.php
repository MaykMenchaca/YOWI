<?php
// Sanitización/validación mínima de inputs de la API.

declare(strict_types=1);

function ds_validate_email(string $email): ?string
{
    $email = trim($email);
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
}

function ds_clean_string(string $value, int $maxLength = 255): string
{
    return mb_substr(trim($value), 0, $maxLength);
}

function ds_to_positive_float($value): float
{
    $f = (float) $value;
    return $f > 0 ? $f : 0.0;
}

function ds_to_positive_int($value): int
{
    $i = (int) $value;
    return $i > 0 ? $i : 0;
}

/**
 * Limpia una URL para usarla como enlace clicable (href).
 * Permite solo esquemas http/https o rutas relativas (sin esquema).
 * Bloquea esquemas peligrosos como javascript: o data: que provocarían XSS.
 * Devuelve null si queda vacía o si el esquema no está permitido.
 */
function ds_clean_url(string $value, int $maxLength = 500): ?string
{
    // Quitar caracteres de control (incluye tab/CR/LF) ANTES de mirar el esquema. Sin
    // esto, "java\tscript:alert(1)" no calza con el regex de abajo (el tab rompe la
    // secuencia "javascript") y la cadena se acepta tal cual — el navegador ignora los
    // caracteres de control al interpretar un esquema, así que sí lo ejecutaría. Mismo
    // endurecimiento que window.DSSec.safeHref en site/assets/js/security-utils.js.
    $value = preg_replace('/[\x00-\x1F\x7F]+/', '', $value) ?? '';
    $value = ds_clean_string($value, $maxLength);
    if ($value === '') {
        return null;
    }
    // Protocolo-relativo ("//host"): hereda el esquema de la página, en la práctica
    // equivale a aceptar cualquier esquema. Rechazarlo también.
    if (str_starts_with($value, '//')) {
        return null;
    }
    // Si la cadena empieza con "esquema:", solo aceptar http/https.
    if (preg_match('/^\s*([a-z][a-z0-9+.\-]*)\s*:/i', $value, $m)) {
        $scheme = strtolower($m[1]);
        if ($scheme !== 'http' && $scheme !== 'https') {
            return null;
        }
    }
    return $value;
}
