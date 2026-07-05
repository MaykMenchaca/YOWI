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
