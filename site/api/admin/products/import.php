<?php
declare(strict_types=1);

require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../lib/Response.php';
require __DIR__ . '/../../lib/AdminSession.php';
require __DIR__ . '/../../lib/Validate.php';
require __DIR__ . '/../../lib/Flavors.php';
require __DIR__ . '/../../lib/Gallery.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') ds_json_error('Método no permitido', 405);
ds_require_rol(DS_ROL_OPERADOR);
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

// Vista previa (F2.2): con preview=1 se ejecuta EXACTAMENTE la misma ruta de código
// (incluida la creación de categorías/marcas) dentro de una transacción que siempre
// termina en rollBack. Así el reporte es 100% fiel (mismos conflictos de UNIQUE,
// mismas categorías resueltas) sin arriesgar divergencia entre "simular" y "aplicar".
$preview    = ($_POST['preview'] ?? '0') === '1';
$replaceAll = ($_POST['replace_all'] ?? '') === '1';

$catCache      = [];   // slug => id  (evita consultar/crear la misma categoría dos veces)
$brandCache    = [];   // slug => true (evita consultar/crear la misma marca dos veces)
$creados       = 0;
$actualizados  = 0;    // ahora significa "cambió algo" (diff campo por campo), no "match encontrado"
$sinCambios    = 0;
$desactivados  = 0;
$catsCreadas   = 0;
$marcasCreadas = 0;
$omitidos      = [];
$filas         = [];   // reporte completo de TODAS las filas procesadas (F2.4 lo consumirá)
$idsTocados    = [];   // ids de productos creados/actualizados/sin_cambios: siguen en el CSV
$linea         = 1;    // encabezados = línea 1
$MAX_FILAS     = 2000;

$addOmitido = function (int $linea, string $motivo, ?string $sku = null, ?string $nombre = null) use (&$omitidos, &$filas): void {
    $omitidos[] = ['fila' => $linea, 'motivo' => $motivo];
    $filas[]    = ['fila' => $linea, 'accion' => 'omitido', 'sku' => $sku, 'nombre' => $nombre, 'campos' => null, 'motivo' => $motivo];
};

// Emparejamiento de producto existente, en orden de prioridad:
//   1) por SKU (si el CSV trae uno y ya existe en algún producto) — permite renombrar
//      sin duplicar, es la identidad estable entre reimportaciones.
//   2) fallback LEGACY: nombre + marca + cantidad + unidad (dos presentaciones del mismo
//      producto, ej. "Creakong" 1000 g y 300 g, son productos distintos, no el mismo).
//      Si este método encuentra y el CSV traía SKU, ese SKU se "bautiza" en el producto.
$findBySku = $pdo->prepare('SELECT id FROM products WHERE sku = ? LIMIT 1');
$findProd  = $pdo->prepare('SELECT id FROM products WHERE nombre = ? AND marca = ? AND cantidad = ? AND unidad <=> ? LIMIT 1');
$findFull  = $pdo->prepare(
    'SELECT nombre, marca, category_id, cantidad, unidad, descripcion, precio, precio_original, stock, imagen, badge, destacado, activo, sku
     FROM products WHERE id = ?'
);
$findCat  = $pdo->prepare('SELECT id FROM categories WHERE slug = ? LIMIT 1');
$insCat   = $pdo->prepare('INSERT INTO categories (nombre, slug, orden) VALUES (?, ?, 0)');
$findBrand = $pdo->prepare('SELECT id FROM brands WHERE slug = ? LIMIT 1');
$insBrand  = $pdo->prepare('INSERT INTO brands (nombre, slug, activo) VALUES (?, ?, 1)');
$insProd  = $pdo->prepare(
    'INSERT INTO products (nombre, marca, category_id, cantidad, unidad, descripcion, precio, precio_original, stock, imagen, badge, destacado, activo, sku)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
// nombre/marca: SIEMPRE se sobreescriben (el SKU permite renombrar sin duplicar; si el
// UPDATE no los tocara, renombrar jamás surtiría efecto). descripcion/badge/sku usan
// COALESCE: celda vacía en el CSV (-> null aquí) = no tocar el valor actual.
$updProdImg = $pdo->prepare(
    'UPDATE products SET nombre=?, marca=?, category_id=?, cantidad=?, unidad=?, descripcion=COALESCE(?, descripcion), precio=?, precio_original=?, stock=?, imagen=?, badge=COALESCE(?, badge), destacado=?, activo=?, sku=COALESCE(?, sku) WHERE id=?'
);
$updProdNoImg = $pdo->prepare(
    'UPDATE products SET nombre=?, marca=?, category_id=?, cantidad=?, unidad=?, descripcion=COALESCE(?, descripcion), precio=?, precio_original=?, stock=?, badge=COALESCE(?, badge), destacado=?, activo=?, sku=COALESCE(?, sku) WHERE id=?'
);

// Sabores (F3.2) y galería (F4.2): "columna vacía = no tocar" también aplica aquí. Estos
// helpers arman una "foto" comparable del ANTES (BD) y el DESPUÉS (CSV ya parseado) solo
// para el reporte de campos cambiados; la escritura real la hacen ds_sync_product_flavors()
// / ds_sync_product_gallery() (site/api/lib/{Flavors,Gallery}.php), compartidas con el
// editor del panel (F3.3/F4.3) para que ambos caminos se comporten igual.
$findFlavors = $pdo->prepare('SELECT slug, nombre, stock, precio FROM product_flavors WHERE product_id = ? ORDER BY slug');
$flavorsSnapshot = static function (int $productId) use ($findFlavors): array {
    $findFlavors->execute([$productId]);
    return array_map(static function ($r) {
        return $r['slug'] . '|' . $r['nombre'] . '|' . ($r['stock'] === null ? '' : (int) $r['stock']) . '|' .
            ($r['precio'] === null ? '' : number_format((float) $r['precio'], 2, '.', ''));
    }, $findFlavors->fetchAll());
};
$flavorsTargetSnapshot = static function (array $parsed): array {
    $rows = [];
    foreach ($parsed as $f) {
        $nombreF = trim((string) $f['nombre']);
        if ($nombreF === '') continue;
        $slugF = ds_slugify_flavor($nombreF);
        if ($slugF === '') continue;
        $rows[$slugF] = $slugF . '|' . mb_substr($nombreF, 0, 80) . '|' .
            ($f['stock'] === null ? '' : max(0, (int) $f['stock'])) . '|' .
            ($f['precio'] === null ? '' : number_format(max(0.0, (float) $f['precio']), 2, '.', ''));
    }
    ksort($rows);
    return array_values($rows);
};
$findImages = $pdo->prepare('SELECT url FROM product_images WHERE product_id = ? ORDER BY orden');
$gallerySnapshot = static function (int $productId) use ($findImages): array {
    $findImages->execute([$productId]);
    return array_map(static fn($r) => $r['url'], $findImages->fetchAll());
};

$pdo->beginTransaction();
try {
    while (($row = fgetcsv($fh, 0, $delimiter)) !== false) {
        $linea++;
        // Saltar filas totalmente vacías (Excel a veces deja líneas en blanco al final).
        if (count(array_filter($row, static fn($c) => trim((string) $c) !== '')) === 0) continue;

        if (($creados + $actualizados + $sinCambios + count($omitidos)) >= $MAX_FILAS) {
            $addOmitido($linea, "Se alcanzó el máximo de $MAX_FILAS filas por importación");
            break;
        }

        $nombre = mb_substr($get($row, $cols, 'nombre'), 0, 255);
        $marca  = mb_substr($get($row, $cols, 'marca'), 0, 120);
        $catNom = $get($row, $cols, 'categoria');
        $precio = $toNumber($get($row, $cols, 'precio'));

        if ($nombre === '' || $marca === '' || $catNom === '') {
            $addOmitido($linea, 'Faltan nombre, marca o categoría');
            continue;
        }
        if ($precio === null || $precio <= 0) {
            $addOmitido($linea, 'Precio inválido o vacío', null, $nombre);
            continue;
        }

        // Resolver / crear categoría.
        $slug = $slugify($catNom);
        if ($slug === '') {
            $addOmitido($linea, 'Nombre de categoría inválido', null, $nombre);
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
                    $addOmitido($linea, 'No se pudo crear la categoría "' . $catNom . '"', null, $nombre);
                    continue; // continuar el while externo (try/catch no anida niveles)
                }
            }
            $catCache[$slug] = $catId;
        }

        // Asegurar que la marca exista en el catálogo de marcas, para poder gestionarla
        // aparte (logo, editar, ocultar). No bloquea la fila si algo falla.
        $bslug = $slugify($marca);
        if ($bslug !== '' && !isset($brandCache[$bslug])) {
            $findBrand->execute([$bslug]);
            if ((int) ($findBrand->fetchColumn() ?: 0) === 0) {
                try {
                    $insBrand->execute([mb_substr($marca, 0, 120), mb_substr($bslug, 0, 140)]);
                    $marcasCreadas++;
                } catch (\Throwable $e) { /* colisión: otra fila ya la creó */ }
            }
            $brandCache[$bslug] = true;
        }

        // Campos opcionales.
        $cantidad    = mb_substr($get($row, $cols, 'cantidad'), 0, 80);
        $unidad      = mb_substr($get($row, $cols, 'unidad'), 0, 20) ?: null;
        $descripcion = mb_substr($get($row, $cols, 'descripcion'), 0, 5000) ?: null;
        $precioOrig  = $toNumber($get($row, $cols, 'precio_original'));
        if ($precioOrig !== null && $precioOrig <= $precio) $precioOrig = null; // solo si es un "tachado" válido
        $stockNum    = $toNumber($get($row, $cols, 'stock')); // '' -> null; '0' -> 0.0
        $stock       = $stockNum === null ? null : max(0, (int) $stockNum);
        // Solo rutas relativas o http/https; esquema peligroso o vacío -> '' (usa placeholder).
        $imagen      = ds_clean_url($get($row, $cols, 'imagen'), 255) ?? '';
        $badge       = mb_substr($get($row, $cols, 'badge'), 0, 30) ?: null;
        $destacado   = $toBool($get($row, $cols, 'destacado'), 0);
        $activo      = $toBool($get($row, $cols, 'activo'), 1);
        $skuRaw      = mb_substr($get($row, $cols, 'sku'), 0, 64);
        $sku         = $skuRaw !== '' ? $skuRaw : null;

        // Sabores: "Chocolate:10:899|Mocha::999|Fresa" -> nombre:stock:precio (los 2
        // últimos opcionales). Celda vacía (null aquí) = no tocar los sabores existentes.
        $saboresRaw    = $get($row, $cols, 'sabores');
        $saboresParsed = $saboresRaw !== '' ? ds_parse_flavors_csv($saboresRaw, $toNumber) : null;

        // Imágenes de galería: rutas/URLs separadas por "|". Celda vacía = no tocar.
        // Si TODAS las URLs de la celda resultan inválidas, se trata igual que vacía
        // (nunca se arriesga a vaciar la galería por un error de tipeo en el CSV).
        $imagenesRaw    = $get($row, $cols, 'imagenes');
        $imagenesParsed = null;
        if ($imagenesRaw !== '') {
            $urlsLimpias = [];
            foreach (explode('|', $imagenesRaw) as $u) {
                $clean = ds_clean_url(trim($u), 255);
                if ($clean !== null) $urlsLimpias[] = $clean;
            }
            $urlsLimpias = array_values(array_unique($urlsLimpias));
            if (!empty($urlsLimpias)) $imagenesParsed = $urlsLimpias;
        }

        // Emparejamiento: SKU primero, fallback legacy después (ver comentario de los prepared
        // statements arriba). Si ambos métodos apuntan a productos EXISTENTES pero DISTINTOS,
        // es una colisión real: el SKU del CSV ya está "tomado" por otro producto -> se omite.
        $findProd->execute([$nombre, $marca, $cantidad, $unidad]);
        $legacyId = (int) ($findProd->fetchColumn() ?: 0);

        $existingId = 0;
        if ($sku !== null) {
            $findBySku->execute([$sku]);
            $skuOwnerId = (int) ($findBySku->fetchColumn() ?: 0);
            if ($skuOwnerId > 0) {
                if ($legacyId > 0 && $legacyId !== $skuOwnerId) {
                    $addOmitido($linea, "El SKU '$sku' ya pertenece a otro producto", $sku, $nombre);
                    continue;
                }
                $existingId = $skuOwnerId; // match por SKU (prioridad 1)
            } else {
                $existingId = $legacyId;   // SKU libre: fallback legacy (prioridad 2); se "bautiza" abajo
            }
        } else {
            $existingId = $legacyId;       // sin SKU en la fila: fallback legacy de siempre
        }

        try {
            if ($existingId > 0) {
                // Diff campo por campo (F2.1): se trae la fila actual completa y se compara
                // contra los valores nuevos YA resueltos con la regla "vacío = no tocar"
                // (mismo valor efectivo que aplicaría el COALESCE del UPDATE).
                $findFull->execute([$existingId]);
                $current = $findFull->fetch();

                $descEfectiva   = $descripcion ?? $current['descripcion'];
                $badgeEfectivo  = $badge ?? $current['badge'];
                $skuEfectivo    = $sku ?? $current['sku'];
                $imagenEfectiva = $imagen !== '' ? $imagen : $current['imagen'];

                $campos = [];
                if ($nombre !== $current['nombre']) $campos[] = 'nombre';
                if ($marca !== $current['marca']) $campos[] = 'marca';
                if ($catId !== (int) $current['category_id']) $campos[] = 'categoria';
                if ($cantidad !== $current['cantidad']) $campos[] = 'cantidad';
                if ($unidad !== $current['unidad']) $campos[] = 'unidad';
                if ($descEfectiva !== $current['descripcion']) $campos[] = 'descripcion';
                if (abs($precio - (float) $current['precio']) > 0.001) $campos[] = 'precio';
                $precioOrigActual = $current['precio_original'] !== null ? (float) $current['precio_original'] : null;
                $precioOrigCambio = ($precioOrig === null) !== ($precioOrigActual === null)
                    || ($precioOrig !== null && $precioOrigActual !== null && abs($precioOrig - $precioOrigActual) > 0.001);
                if ($precioOrigCambio) $campos[] = 'precio_original';
                if ($stock !== $current['stock']) $campos[] = 'stock';
                if ($imagenEfectiva !== $current['imagen']) $campos[] = 'imagen';
                if ($badgeEfectivo !== $current['badge']) $campos[] = 'badge';
                if ($destacado !== (int) $current['destacado']) $campos[] = 'destacado';
                if ($activo !== (int) $current['activo']) $campos[] = 'activo';
                if ($skuEfectivo !== $current['sku']) $campos[] = 'sku';
                if ($saboresParsed !== null && $flavorsSnapshot($existingId) !== $flavorsTargetSnapshot($saboresParsed)) {
                    $campos[] = 'sabores';
                }
                if ($imagenesParsed !== null && $gallerySnapshot($existingId) !== $imagenesParsed) {
                    $campos[] = 'imagenes';
                }

                $idsTocados[] = $existingId; // sigue en el CSV, no se desactiva con replace_all

                if (empty($campos)) {
                    $sinCambios++;
                    $filas[] = ['fila' => $linea, 'accion' => 'sin_cambios', 'sku' => $skuEfectivo, 'nombre' => $nombre, 'campos' => null, 'motivo' => null];
                } else {
                    // Upsert: se actualiza. La imagen solo se pisa si el CSV trae una; si va
                    // vacía, se conserva (dos prepared statements distintos).
                    if ($imagen !== '') {
                        $updProdImg->execute([$nombre, $marca, $catId, $cantidad, $unidad, $descripcion, $precio, $precioOrig, $stock, $imagen, $badge, $destacado, $activo, $sku, $existingId]);
                    } else {
                        $updProdNoImg->execute([$nombre, $marca, $catId, $cantidad, $unidad, $descripcion, $precio, $precioOrig, $stock, $badge, $destacado, $activo, $sku, $existingId]);
                    }
                    if ($saboresParsed !== null) ds_sync_product_flavors($pdo, $existingId, $saboresParsed);
                    if ($imagenesParsed !== null) ds_sync_product_gallery($pdo, $existingId, $imagenesParsed);
                    $actualizados++;
                    $filas[] = ['fila' => $linea, 'accion' => 'actualizado', 'sku' => $skuEfectivo, 'nombre' => $nombre, 'campos' => $campos, 'motivo' => null];
                }
            } else {
                $imagenFinal = $imagen !== '' ? $imagen : 'assets/img/producto-placeholder.svg';
                $insProd->execute([$nombre, $marca, $catId, $cantidad, $unidad, $descripcion, $precio, $precioOrig, $stock, $imagenFinal, $badge, $destacado, $activo, $sku]);
                $newId = (int) $pdo->lastInsertId();
                if ($saboresParsed !== null) ds_sync_product_flavors($pdo, $newId, $saboresParsed);
                if ($imagenesParsed !== null) ds_sync_product_gallery($pdo, $newId, $imagenesParsed);
                $idsTocados[] = $newId;
                $creados++;
                $filas[] = ['fila' => $linea, 'accion' => 'creado', 'sku' => $sku, 'nombre' => $nombre, 'campos' => null, 'motivo' => null];
            }
        } catch (\Throwable $e) {
            $isDupSku = $e instanceof \PDOException && ((int) $e->getCode() === 23000 || ($e->errorInfo[1] ?? null) === 1062);
            if ($isDupSku && $sku !== null) {
                $addOmitido($linea, "El SKU '$sku' ya pertenece a otro producto", $sku, $nombre);
            } else {
                $addOmitido($linea, 'Error al guardar la fila', $sku, $nombre);
            }
            continue;
        }
    }
    fclose($fh);

    // "Reemplazar todo" (F2.3): ya NO borra. Desactiva (activo=0) los productos que
    // existían en la BD pero no aparecieron en este CSV (no están en $idsTocados).
    // Protección: si $idsTocados quedó vacío (CSV vacío/corrupto -> todo omitido), no se
    // desactiva nada, para no vaciar el catálogo por accidente.
    if ($replaceAll && !empty($idsTocados)) {
        $idsUnicos    = array_values(array_unique($idsTocados));
        $placeholders = implode(',', array_fill(0, count($idsUnicos), '?'));
        if ($preview) {
            // En preview solo se calcula cuántos se desactivarían; no se escribe.
            $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM products WHERE id NOT IN ($placeholders) AND activo = 1");
            $stmtCount->execute($idsUnicos);
            $desactivados = (int) $stmtCount->fetchColumn();
        } else {
            $stmtDeact = $pdo->prepare("UPDATE products SET activo = 0 WHERE id NOT IN ($placeholders) AND activo = 1");
            $stmtDeact->execute($idsUnicos);
            $desactivados = $stmtDeact->rowCount();
        }
    }

    if ($preview) {
        $pdo->rollBack();
    } else {
        $pdo->commit();
    }
} catch (\Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if (is_resource($fh)) fclose($fh);
    ds_json_error('Error interno al procesar la importación', 500);
}

ds_json_success([
    'creados'            => $creados,
    'actualizados'       => $actualizados,   // ahora significa "cambió algo", no "match encontrado"
    'sin_cambios'        => $sinCambios,
    'desactivados'       => $desactivados,   // reemplaza el sentido de 'borrados'
    'categorias_creadas' => $catsCreadas,
    'marcas_creadas'     => $marcasCreadas,
    'borrados'           => 0,               // ya no se borra nada; se deja en 0 por compatibilidad de forma
    'omitidos'           => $omitidos,
    'filas'              => $filas,          // [{fila, accion, sku, nombre, campos, motivo}, ...] — todas las filas
    'preview'            => $preview,
    'total_procesadas'   => $creados + $actualizados + $sinCambios + count($omitidos),
]);
