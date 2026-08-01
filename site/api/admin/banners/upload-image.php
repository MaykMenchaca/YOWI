<?php
declare(strict_types=1);

require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../lib/Response.php';
require __DIR__ . '/../../lib/AdminSession.php';
require __DIR__ . '/../../lib/Image.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') ds_json_error('Método no permitido', 405);
ds_require_admin();
ds_admin_csrf_check($_POST['csrf_token'] ?? null);

if (empty($_FILES['imagen']) || $_FILES['imagen']['error'] !== UPLOAD_ERR_OK) {
    $code = $_FILES['imagen']['error'] ?? -1;
    ds_json_error("Error al recibir el archivo (código $code)", 400);
}

$file     = $_FILES['imagen'];
$maxBytes = 8 * 1024 * 1024; // los banners suelen ser imágenes grandes (alta resolución)

if ($file['size'] > $maxBytes) {
    ds_json_error('El archivo supera el límite de 8 MB', 400);
}

// Detección del MIME real, con fallbacks si fileinfo no está disponible.
$mime = null;
if (class_exists('finfo')) {
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']) ?: null;
} elseif (function_exists('mime_content_type')) {
    $mime = mime_content_type($file['tmp_name']) ?: null;
}
if ($mime === null) {
    $info = @getimagesize($file['tmp_name']);
    $mime = $info['mime'] ?? null;
}
$allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

if ($mime === null || !array_key_exists($mime, $allowed)) {
    ds_json_error('Solo se permiten imágenes JPG, PNG o WebP', 400);
}

$ext      = $allowed[$mime];
$safeName = bin2hex(random_bytes(12)) . '.' . $ext;
$destDir  = __DIR__ . '/../../../assets/img/banners/';

if (!is_dir($destDir) && !mkdir($destDir, 0755, true)) {
    ds_json_error('No se pudo crear el directorio de imágenes', 500);
}

// La carpeta de uploads nunca ejecuta scripts (defensa en profundidad).
$htaccess = $destDir . '.htaccess';
if (!file_exists($htaccess)) {
    @file_put_contents(
        $htaccess,
        "php_flag engine off\n"
        . "RemoveHandler .php .phtml .php3 .php4 .php5 .php7 .phps .pht\n"
        . "<FilesMatch \"\\.(php|phtml|php3|php4|php5|php7|phps|pht)\$\">\n"
        . "    Require all denied\n"
        . "</FilesMatch>\n"
    );
}

if (!ds_reencode_image($file['tmp_name'], $destDir . $safeName, $mime)) {
    if (!move_uploaded_file($file['tmp_name'], $destDir . $safeName)) {
        ds_json_error('Error al guardar la imagen en el servidor', 500);
    }
}

ds_json_success(['url' => 'assets/img/banners/' . $safeName]);
