<?php
declare(strict_types=1);

// Sabores por producto: solo nombre. No tienen precio ni stock propios — el pedido
// nunca se bloquea por disponibilidad y todos los sabores cuestan lo mismo que el
// producto (se confirma por WhatsApp). Las columnas stock/precio de product_flavors
// se dejan en NULL; siguen existiendo en la BD por si algún día se quiere revertir,
// pero nada las lee.
// Usado por el importador de CSV (F3.2) y por el editor del panel (F3.3), para que
// ambos caminos apliquen exactamente la misma regla de sincronización.

function ds_slugify_flavor(string $nombre): string
{
    return trim((string) preg_replace(
        '/[^a-z0-9]+/', '-',
        strtolower((string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nombre))
    ), '-');
}

/**
 * Reemplaza el conjunto completo de sabores de un producto por $parsedFlavors.
 * $parsedFlavors: lista de ['nombre' => string, ...] — cualquier 'stock'/'precio' que
 * traiga se ignora (se guarda siempre NULL).
 *
 * Empareja por slug: si ya existe un sabor con ese slug en el producto, se actualiza
 * (nombre/orden); si no, se crea. Los sabores existentes que ya NO estén
 * en la lista nueva se BORRAN (a diferencia de los productos, un sabor es un
 * sub-recurso del producto: no tiene sentido "desactivarlo" en vez de quitarlo, y
 * order_items.sabor solo guarda el nombre como texto, así que borrar un sabor nunca
 * rompe pedidos ya hechos).
 *
 * Debe llamarse dentro de una transacción ya abierta por quien invoca (import.php y
 * flavors.php ya envuelven su trabajo en beginTransaction/commit/rollBack).
 */
function ds_sync_product_flavors(PDO $pdo, int $productId, array $parsedFlavors): void
{
    // Deduplicar por slug dentro de la lista nueva (si el CSV o el panel mandan el
    // mismo nombre dos veces, se queda con la última aparición).
    $bySlug = [];
    $orden = 0;
    foreach ($parsedFlavors as $f) {
        $nombre = trim((string) ($f['nombre'] ?? ''));
        if ($nombre === '') {
            continue;
        }
        $slug = ds_slugify_flavor($nombre);
        if ($slug === '') {
            continue;
        }
        $bySlug[$slug] = [
            'nombre' => mb_substr($nombre, 0, 80),
            'orden'  => $orden++,
        ];
    }

    $findExisting = $pdo->prepare('SELECT id, slug FROM product_flavors WHERE product_id = ?');
    $findExisting->execute([$productId]);
    $existing = [];
    foreach ($findExisting->fetchAll() as $row) {
        $existing[$row['slug']] = (int) $row['id'];
    }

    // stock/precio siempre NULL: la columna se queda en la BD (no se toca el esquema)
    // pero nada la escribe con un valor real ya.
    $insert = $pdo->prepare(
        'INSERT INTO product_flavors (product_id, nombre, slug, stock, precio, orden, activo) VALUES (?, ?, ?, NULL, NULL, ?, 1)'
    );
    $update = $pdo->prepare(
        'UPDATE product_flavors SET nombre = ?, stock = NULL, precio = NULL, orden = ?, activo = 1 WHERE id = ?'
    );

    foreach ($bySlug as $slug => $f) {
        if (isset($existing[$slug])) {
            $update->execute([$f['nombre'], $f['orden'], $existing[$slug]]);
        } else {
            $insert->execute([$productId, $f['nombre'], $slug, $f['orden']]);
        }
    }

    $slugsNuevos = array_keys($bySlug);
    if (!empty($slugsNuevos)) {
        $placeholders = implode(',', array_fill(0, count($slugsNuevos), '?'));
        $del = $pdo->prepare("DELETE FROM product_flavors WHERE product_id = ? AND slug NOT IN ($placeholders)");
        $del->execute(array_merge([$productId], $slugsNuevos));
    } else {
        // Lista vacía tras limpiar: no queda ningún sabor válido, se borran todos.
        $pdo->prepare('DELETE FROM product_flavors WHERE product_id = ?')->execute([$productId]);
    }
}

/**
 * Parsea la columna CSV `sabores`: acepta tanto el formato nuevo "Chocolate|Mocha|Fresa"
 * como el viejo "Chocolate:10:899|Mocha::999|Fresa" (por compatibilidad con CSVs ya
 * exportados) -> [['nombre'=>'Chocolate'], ['nombre'=>'Mocha'], ['nombre'=>'Fresa']].
 * Si una entrada trae ":stock:precio" se ignora esa parte, solo se usa el nombre.
 */
function ds_parse_flavors_csv(string $raw, callable $toNumber): array
{
    $raw = trim($raw);
    if ($raw === '') {
        return [];
    }
    $out = [];
    foreach (explode('|', $raw) as $entry) {
        $entry = trim($entry);
        if ($entry === '') {
            continue;
        }
        $parts = explode(':', $entry, 3);
        $nombre = trim($parts[0] ?? '');
        if ($nombre === '') {
            continue;
        }
        $out[] = ['nombre' => $nombre];
    }
    return $out;
}
