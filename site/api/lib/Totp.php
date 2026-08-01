<?php
// TOTP (RFC 6238) en PHP puro, sin dependencias. Compatible con Google Authenticator,
// Authy, Microsoft Authenticator, etc. HMAC-SHA1, paso de 30 s, códigos de 6 dígitos.

declare(strict_types=1);

/** Genera un secreto aleatorio en base32 (longitud por defecto 32 chars = 160 bits). */
function ds_totp_secret(int $length = 32): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; // base32 RFC 4648
    $bytes = random_bytes($length);
    $out = '';
    for ($i = 0; $i < $length; $i++) {
        $out .= $alphabet[ord($bytes[$i]) & 31];
    }
    return $out;
}

/** Decodifica una cadena base32 a binario. Devuelve '' si hay caracteres inválidos. */
function ds_base32_decode(string $b32): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $b32 = strtoupper(trim($b32));
    $b32 = rtrim($b32, '=');
    $buffer = 0; $bitsLeft = 0; $out = '';
    $len = strlen($b32);
    for ($i = 0; $i < $len; $i++) {
        $val = strpos($alphabet, $b32[$i]);
        if ($val === false) {
            return '';
        }
        $buffer = ($buffer << 5) | $val;
        $bitsLeft += 5;
        if ($bitsLeft >= 8) {
            $bitsLeft -= 8;
            $out .= chr(($buffer >> $bitsLeft) & 0xFF);
        }
    }
    return $out;
}

/** Calcula el código TOTP de 6 dígitos para un secreto base32 en un contador dado. */
function ds_totp_code(string $secretB32, int $counter): string
{
    $key = ds_base32_decode($secretB32);
    if ($key === '') {
        return '';
    }
    // Contador de 8 bytes big-endian.
    $binCounter = pack('N*', 0) . pack('N*', $counter);
    $hash = hash_hmac('sha1', $binCounter, $key, true);
    $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
    $part = (ord($hash[$offset]) & 0x7F) << 24
          | (ord($hash[$offset + 1]) & 0xFF) << 16
          | (ord($hash[$offset + 2]) & 0xFF) << 8
          | (ord($hash[$offset + 3]) & 0xFF);
    $code = $part % 1000000;
    return str_pad((string) $code, 6, '0', STR_PAD_LEFT);
}

/**
 * Verifica un código TOTP de 6 dígitos contra el secreto, con una ventana de ±1 paso
 * (tolera desfases de reloj de hasta 30 s). Comparación en tiempo constante.
 */
function ds_totp_verify(string $secretB32, string $code, int $window = 1, ?int $now = null): bool
{
    $code = preg_replace('/\D+/', '', $code);
    if (strlen($code) !== 6) {
        return false;
    }
    $now = $now ?? time();
    $timeStep = (int) floor($now / 30);
    for ($i = -$window; $i <= $window; $i++) {
        $candidate = ds_totp_code($secretB32, $timeStep + $i);
        if ($candidate !== '' && hash_equals($candidate, $code)) {
            return true;
        }
    }
    return false;
}

/** Construye la URI otpauth:// para generar el QR en una app autenticadora. */
function ds_totp_uri(string $secretB32, string $cuenta, string $emisor): string
{
    $label = rawurlencode($emisor . ':' . $cuenta);
    $params = http_build_query([
        'secret' => $secretB32,
        'issuer' => $emisor,
        'algorithm' => 'SHA1',
        'digits' => 6,
        'period' => 30,
    ]);
    return "otpauth://totp/{$label}?{$params}";
}
