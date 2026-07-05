<?php
// Token CSRF: generado en sesión, validado en cada POST con hash_equals().

declare(strict_types=1);

function ds_csrf_token(): string
{
    ds_session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function ds_csrf_check(?string $submitted): void
{
    ds_session_start();
    $expected = $_SESSION['csrf_token'] ?? '';
    if ($submitted === null || $expected === '' || !hash_equals($expected, $submitted)) {
        ds_json_error('Token de seguridad inválido', 403);
    }
}
