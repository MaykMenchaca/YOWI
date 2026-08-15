<?php
declare(strict_types=1);

// Cifrado simétrico en reposo (AEAD) para datos sensibles en columnas de la BD — hoy solo
// admins.totp_secret. sodium_crypto_secretbox: una sola llamada, nonce + MAC combinados por
// libsodium (sin parámetro de tag por referencia como openssl_encrypt en modo GCM, una fuente
// típica de errores). Requiere ext-sodium, incluida en PHP core desde 7.2 — verificar que esté
// habilitada en el hosting real antes de depender de esto (ver docs/despliegue-hostinger.md).

/** Devuelve la clave de 32 bytes crudos, decodificada desde TOTP_ENCRYPTION_KEY (base64). */
function ds_crypto_key(): string
{
    static $key = null;
    if ($key !== null) {
        return $key;
    }
    $env = ds_get_env();
    $b64 = (string) ($env['TOTP_ENCRYPTION_KEY'] ?? '');
    $raw = $b64 !== '' ? base64_decode($b64, true) : false;
    if ($raw === false || strlen($raw) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
        throw new RuntimeException(
            'TOTP_ENCRYPTION_KEY inválida o ausente en env.php — generar con: ' .
            'php -r "echo base64_encode(random_bytes(32));"'
        );
    }
    $key = $raw;
    return $key;
}

/** Cifra texto plano. Devuelve base64(nonce(24) . secretbox(plaintext)). */
function ds_encrypt(string $plaintext): string
{
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $cipher = sodium_crypto_secretbox($plaintext, $nonce, ds_crypto_key());
    return base64_encode($nonce . $cipher);
}

/** Descifra un valor producido por ds_encrypt(). Devuelve null si algo no cuadra (clave
 *  equivocada, formato inválido, dato manipulado) — nunca lanza a media petición de
 *  login/2FA; el llamador decide cómo tratar un null (como "código incorrecto", por ejemplo). */
function ds_decrypt(string $ciphertext): ?string
{
    $raw = base64_decode($ciphertext, true);
    $minLen = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES;
    if ($raw === false || strlen($raw) < $minLen) {
        return null;
    }
    $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $cipher = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $plain = sodium_crypto_secretbox_open($cipher, $nonce, ds_crypto_key());
    return $plain === false ? null : $plain;
}
