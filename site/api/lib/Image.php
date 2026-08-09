<?php
// Re-encodado de imágenes subidas con GD. Al re-generar la imagen desde sus píxeles se
// descarta cualquier contenido incrustado (payloads PHP en "polyglots", EXIF con scripts,
// datos tras el fin del archivo). Defensa en profundidad sobre la subida de imágenes.

declare(strict_types=1);

/**
 * Re-encoda $src a $dest según el MIME (jpeg/png/webp). Conserva transparencia.
 * Devuelve true si se re-encodó; false si GD no está disponible o el formato no aplica.
 * En ese caso el llamador debe RECHAZAR la subida (no guardar el archivo crudo), para no
 * perder el saneamiento anti-polyglot.
 */
// Techo de píxeles antes de decodificar. Sin esto, un archivo válido y pequeño en disco
// (un PNG de 30000×30000 comprime a pocos KB) puede pedirle a GD que reserve ~ancho×alto×4
// bytes en memoria (varios GB) al decodificarlo — una "bomba de descompresión" que agota
// memory_limit o tumba el proceso, con solo sesión de admin (quien sube imágenes).
const DS_MAX_IMAGE_PIXELS = 40_000_000; // p. ej. 6300×6300, de sobra para cualquier foto de producto

function ds_reencode_image(string $src, string $dest, string $mime): bool
{
    if (!function_exists('imagecreatefromjpeg')) {
        return false; // GD no disponible
    }

    $info = @getimagesize($src);
    if ($info === false || $info[0] <= 0 || $info[1] <= 0) {
        return false;
    }
    if ($info[0] * $info[1] > DS_MAX_IMAGE_PIXELS) {
        return false;
    }

    switch ($mime) {
        case 'image/jpeg':
            $img = @imagecreatefromjpeg($src);
            break;
        case 'image/png':
            $img = @imagecreatefrompng($src);
            break;
        case 'image/webp':
            $img = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($src) : false;
            break;
        default:
            return false;
    }
    if (!$img) {
        return false;
    }

    // Conservar canal alfa en PNG/WebP.
    if ($mime === 'image/png' || $mime === 'image/webp') {
        imagealphablending($img, false);
        imagesavealpha($img, true);
    }

    switch ($mime) {
        case 'image/jpeg':
            $ok = imagejpeg($img, $dest, 85);
            break;
        case 'image/png':
            $ok = imagepng($img, $dest, 6);
            break;
        case 'image/webp':
            $ok = function_exists('imagewebp') ? imagewebp($img, $dest, 85) : false;
            break;
        default:
            $ok = false;
    }
    imagedestroy($img);
    return (bool) $ok;
}
