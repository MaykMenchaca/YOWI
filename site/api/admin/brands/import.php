<?php
declare(strict_types=1);

require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../lib/Response.php';
require __DIR__ . '/../../lib/AdminSession.php';
require __DIR__ . '/../../lib/Validate.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') ds_json_error('Método no permitido', 405);
ds_require_admin();
ds_admin_csrf_check($_POST['csrf_token'] ?? null);

// ── Recepción del archivo ─────────────────────────────────────────────────────
if (empty($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
    $code = $_FILES['csv']['error'] ?? -1;
    ds_json_error("Error al recibir el archivo CSV (código $code)", 400);
}
if ($_FILES['csv']['size'] > 4 * 1024 * 1024) {
    ds_json_error('El archivo supera el límite de 4 MB', 400);
}

$content = file_get_contents($_FILES['csv']['tmp_name']);
if ($content === false || trim($content) === '') {
    ds_json_error('El archivo está vacío', 400);
}
$content = preg_replace('/^\xEF\xBB\xBF/', '', $content); // quitar BOM de Excel

// ── Helpers de normalización ──────────────────────────────────────────────────
$normHeader = static function (string $h): string {
    $h = strtolower(trim($h));
    $h = (string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $h);
    $h = preg_replace('/[^a-z0-9]+/', '_', $h);
    return trim((string) $h, '_');
};

$toBool = static function ($v, int $default): int {
    $s = strtolower(trim((string) $v));
    if ($s === '') return $default;
    if (in_array($s, ['1', 'si', 'sí', 'yes', 'true', 'x', 'activo', 'visible'], true)) return 1;
    if (in_array($s, ['0', 'no', 'false', 'oculto', 'inactivo'], true)) return 0;
    return $default;
};

$slugify = static function (string $nombre): string {
    return trim((string) preg_replace(
        '/[^a-z0-9]+/', '-',
        strtolower((string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nombre))
    ), '-');
};

// ── Lectura del CSV (detecta separador , o ;) ─────────────────────────────────
$firstLine = strtok($content, "\r\n") ?: '';
$delimiter = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';

$fh = fopen('php://temp', 'r+');
fwrite($fh, $content);
rewind($fh);

$rawHeaders = fgetcsv($fh, 0, $delimiter);
if (!$rawHeaders) {
    fclose($fh);
    ds_json_error('No pude leer los encabezados del CSV', 400);
}
$cols = [];
foreach ($rawHeaders as $i => $h) {
    $cols[$normHeader((string) $h)] = $i;
}
if (!array_key_exists('nombre', $cols)) {
    fclose($fh);
    ds_json_error("Falta la columna obligatoria 'nombre' en el CSV", 400);
}
$get = static function (array $row, array $cols, string $key): string {
    return array_key_exists($key, $cols) && isset($row[$cols[$key]]) ? trim((string) $row[$cols[$key]]) : '';
};

$pdo = ds_get_pdo();

$creados      = 0;
$actualizados = 0;
$omitidos     = [];
$linea        = 1;
$MAX_FILAS    = 2000;

$find      = $pdo->prepare('SELECT id FROM brands WHERE slug = ? LIMIT 1');
$ins       = $pdo->prepare('INSERT INTO brands (nombre, slug, imagen, enlace, orden, activo) VALUES (?, ?, ?, ?, ?, ?)');
$updImg    = $pdo->prepare('UPDATE brands SET nombre=?, imagen=?, enlace=?, orden=?, activo=? WHERE id=?');
$updNoImg  = $pdo->prepare('UPDATE brands SET nombre=?, enlace=?, orden=?, activo=? WHERE id=?');

while (($row = fgetcsv($fh, 0, $delimiter)) !== false) {
    $linea++;
    if (count(array_filter($row, static fn($c) => trim((string) $c) !== '')) === 0) continue;

    if (($creados + $actualizados + count($omitidos)) >= $MAX_FILAS) {
        $omitidos[] = ['fila' => $linea, 'motivo' => "Se alcanzó el máximo de $MAX_FILAS filas por importación"];
        break;
    }

    $nombre = mb_substr($get($row, $cols, 'nombre'), 0, 120);
    if ($nombre === '') {
        $omitidos[] = ['fila' => $linea, 'motivo' => 'Falta el nombre de la marca'];
        continue;
    }
    $slug = $slugify($nombre);
    if ($slug === '') {
        $omitidos[] = ['fila' => $linea, 'motivo' => 'Nombre de marca inválido'];
        continue;
    }

    $imagen = mb_substr($get($row, $cols, 'imagen'), 0, 255);
    $enlace = ds_clean_url($get($row, $cols, 'enlace'), 500);
    $ordenN = (int) $get($row, $cols, 'orden');
    $orden  = $ordenN > 0 ? $ordenN : 0;
    $activo = $toBool($get($row, $cols, 'activo'), 1);

    try {
        $find->execute([$slug]);
        $existingId = (int) ($find->fetchColumn() ?: 0);

        if ($existingId > 0) {
            if ($imagen !== '') {
                $updImg->execute([$nombre, $imagen, $enlace, $orden, $activo, $existingId]);
            } else {
                $updNoImg->execute([$nombre, $enlace, $orden, $activo, $existingId]);
            }
            $actualizados++;
        } else {
            $ins->execute([$nombre, $slug, $imagen !== '' ? $imagen : null, $enlace, $orden, $activo]);
            $creados++;
        }
    } catch (\Throwable $e) {
        $omitidos[] = ['fila' => $linea, 'motivo' => 'Error al guardar la fila'];
        continue;
    }
}
fclose($fh);

ds_json_success([
    'creados'          => $creados,
    'actualizados'     => $actualizados,
    'omitidos'         => $omitidos,
    'total_procesadas' => $creados + $actualizados + count($omitidos),
]);
