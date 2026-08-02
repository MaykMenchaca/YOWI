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
function ds_reencode_image(string $src, string $dest, string $mime): bool
{
    if (!function_exists('imagecreatefromjpeg')) {
        return false; // GD no disponible
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
