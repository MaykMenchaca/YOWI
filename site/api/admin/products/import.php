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
// Excel suele anteponer un BOM UTF-8: lo quitamos para no ensuciar el primer encabezado.
$content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

// ── Helpers de normalización ──────────────────────────────────────────────────
$normHeader = static function (string $h): string {
    $h = strtolower(trim($h));
    $h = (string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $h); // categoría -> categoria
    $h = preg_replace('/[^a-z0-9]+/', '_', $h);
    return trim((string) $h, '_');
};

$toNumber = static function ($v): ?float {
    $s = str_replace(' ', '', trim((string) $v));
    if ($s === '') return null;
    if (strpos($s, ',') !== false && strpos($s, '.') !== false) {
        $s = str_replace(',', '', $s);            // "1.299,90" ó "1,299.90": la coma es de miles
    } elseif (strpos($s, ',') !== false) {
        $s = str_replace(',', '.', $s);           // "199,90" -> "199.90" (coma decimal)
    }
    return is_numeric($s) ? (float) $s : null;
};

$toBool = static function ($v, int $default): int {
    $s = strtolower(trim((string) $v));
    if ($s === '') return $default;
    if (in_array($s, ['1', 'si', 'sí', 'yes', 'true', 'x', 'activo', 'destacado', 'visible'], true)) return 1;
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
foreach (['nombre', 'marca', 'categoria', 'precio'] as $req) {
    if (!array_key_exists($req, $cols)) {
        fclose($fh);
        ds_json_error("Falta la columna obligatoria '$req' en el CSV", 400);
    }
}
$get = static function (array $row, array $cols, string $key): string {
    return array_key_exists($key, $cols) && isset($row[$cols[$key]]) ? trim((string) $row[$cols[$key]]) : '';
};

$pdo = ds_get_pdo();

// Reemplazo total del catálogo (opcional): borra todos los productos ANTES de importar.
// Se hace aquí, con los encabezados ya validados, para que un archivo malformado nunca
// borre nada. Seguro: order_items.producto_id es ON DELETE SET NULL (no rompe pedidos).
$borrados = 0;
if (($_POST['replace_all'] ?? '') === '1') {
    $borrados = (int) $pdo->exec('DELETE FROM products');
}

$catCache      = [];   // slug => id  (evita consultar/crear la misma categoría dos veces)
$creados       = 0;
$actualizados  = 0;
$catsCreadas   = 0;
$omitidos      = [];
$linea         = 1;    // encabezados = línea 1
$MAX_FILAS     = 2000;

// El upsert distingue por nombre + marca + cantidad: dos presentaciones del mismo
// producto (ej. "Creakong" 1000 g y 300 g) son productos distintos, no el mismo.
$findProd = $pdo->prepare('SELECT id FROM products WHERE nombre = ? AND marca = ? AND cantidad = ? LIMIT 1');
$findCat  = $pdo->prepare('SELECT id FROM categories WHERE slug = ? LIMIT 1');
$insCat   = $pdo->prepare('INSERT INTO categories (nombre, slug, orden) VALUES (?, ?, 0)');
$insProd  = $pdo->prepare(
    'INSERT INTO products (nombre, marca, category_id, cantidad, descripcion, precio, precio_original, stock, imagen, badge, destacado, activo)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
$updProdImg = $pdo->prepare(
    'UPDATE products SET category_id=?, cantidad=?, descripcion=?, precio=?, precio_original=?, stock=?, imagen=?, badge=?, destacado=?, activo=? WHERE id=?'
);
$updProdNoImg = $pdo->prepare(
    'UPDATE products SET category_id=?, cantidad=?, descripcion=?, precio=?, precio_original=?, stock=?, badge=?, destacado=?, activo=? WHERE id=?'
);

while (($row = fgetcsv($fh, 0, $delimiter)) !== false) {
    $linea++;
    // Saltar filas totalmente vacías (Excel a veces deja líneas en blanco al final).
    if (count(array_filter($row, static fn($c) => trim((string) $c) !== '')) === 0) continue;

    if (($creados + $actualizados + count($omitidos)) >= $MAX_FILAS) {
        $omitidos[] = ['fila' => $linea, 'motivo' => "Se alcanzó el máximo de $MAX_FILAS filas por importación"];
        break;
    }

    $nombre = mb_substr($get($row, $cols, 'nombre'), 0, 255);
    $marca  = mb_substr($get($row, $cols, 'marca'), 0, 120);
    $catNom = $get($row, $cols, 'categoria');
    $precio = $toNumber($get($row, $cols, 'precio'));

    if ($nombre === '' || $marca === '' || $catNom === '') {
        $omitidos[] = ['fila' => $linea, 'motivo' => 'Faltan nombre, marca o categoría'];
        continue;
    }
    if ($precio === null || $precio <= 0) {
        $omitidos[] = ['fila' => $linea, 'motivo' => 'Precio inválido o vacío'];
        continue;
    }

    // Resolver / crear categoría.
    $slug = $slugify($catNom);
    if ($slug === '') {
        $omitidos[] = ['fila' => $linea, 'motivo' => 'Nombre de categoría inválido'];
        continue;
    }
    if (isset($catCache[$slug])) {
        $catId = $catCache[$slug];
    } else {
        $findCat->execute([$slug]);
        $catId = (int) ($findCat->fetchColumn() ?: 0);
        if ($catId === 0) {
            try {
                $insCat->execute([mb_substr($catNom, 0, 100), mb_substr($slug, 0, 110)]);
                $catId = (int) $pdo->lastInsertId();
                $catsCreadas++;
            } catch (\Throwable $e) {
                $omitidos[] = ['fila' => $linea, 'motivo' => 'No se pudo crear la categoría "' . $catNom . '"'];
                continue;
            }
        }
        $catCache[$slug] = $catId;
    }

    // Campos opcionales.
    $cantidad    = mb_substr($get($row, $cols, 'cantidad'), 0, 80);
    $descripcion = mb_substr($get($row, $cols, 'descripcion'), 0, 5000) ?: null;
    $precioOrig  = $toNumber($get($row, $cols, 'precio_original'));
    if ($precioOrig !== null && $precioOrig <= $precio) $precioOrig = null; // solo si es un "tachado" válido
    $stockNum    = $toNumber($get($row, $cols, 'stock'));
    $stock       = $stockNum !== null && $stockNum > 0 ? (int) $stockNum : 0;
    $imagen      = mb_substr($get($row, $cols, 'imagen'), 0, 255);
    $badge       = mb_substr($get($row, $cols, 'badge'), 0, 30) ?: null;
    $destacado   = $toBool($get($row, $cols, 'destacado'), 0);
    $activo      = $toBool($get($row, $cols, 'activo'), 1);

    try {
        $findProd->execute([$nombre, $marca, $cantidad]);
        $existingId = (int) ($findProd->fetchColumn() ?: 0);

        if ($existingId > 0) {
            // Upsert: se actualiza. La imagen solo se pisa si el CSV trae una; si va vacía, se conserva.
            if ($imagen !== '') {
                $updProdImg->execute([$catId, $cantidad, $descripcion, $precio, $precioOrig, $stock, $imagen, $badge, $destacado, $activo, $existingId]);
            } else {
                $updProdNoImg->execute([$catId, $cantidad, $descripcion, $precio, $precioOrig, $stock, $badge, $destacado, $activo, $existingId]);
            }
            $actualizados++;
        } else {
            $imagenFinal = $imagen !== '' ? $imagen : 'assets/img/producto-placeholder.svg';
            $insProd->execute([$nombre, $marca, $catId, $cantidad, $descripcion, $precio, $precioOrig, $stock, $imagenFinal, $badge, $destacado, $activo]);
            $creados++;
        }
    } catch (\Throwable $e) {
        $omitidos[] = ['fila' => $linea, 'motivo' => 'Error al guardar la fila'];
        continue;
    }
}
fclose($fh);

ds_json_success([
    'creados'            => $creados,
    'actualizados'       => $actualizados,
    'categorias_creadas' => $catsCreadas,
    'borrados'           => $borrados,
    'omitidos'           => $omitidos,
    'total_procesadas'   => $creados + $actualizados + count($omitidos),
]);
