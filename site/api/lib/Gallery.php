<?php
declare(strict_types=1);

// Galería de imágenes adicionales por producto (products.imagen sigue siendo la
// principal, esta tabla es solo las extra). Usada por el importador de CSV (F4.2).

/**
 * Reemplaza el conjunto completo de imágenes de galería de un producto por $urls
 * (lista ordenada de URLs ya validadas con ds_clean_url). Empareja por URL: si ya
 * existe, solo se actualiza su orden; si no, se crea. Las existentes que ya NO estén
 * en la lista nueva se BORRAN. Con lista vacía, borra todas — quien llama decide si
 * eso es lo que quiere (el importador de CSV nunca llama con lista vacía: una celda
 * `imagenes` vacía significa "no tocar" y ni siquiera llama a esta función).
 *
 * Debe llamarse dentro de una transacción ya abierta por quien invoca.
 */
function ds_sync_product_gallery(PDO $pdo, int $productId, array $urls): void
{
    // Dedup preservando el primer orden de aparición.
    $seen = [];
    $ordered = [];
    foreach ($urls as $u) {
        $u = trim((string) $u);
        if ($u === '' || isset($seen[$u])) {
            continue;
        }
        $seen[$u] = true;
        $ordered[] = $u;
    }

    $findExisting = $pdo->prepare('SELECT id, url FROM product_images WHERE product_id = ?');
    $findExisting->execute([$productId]);
    $existingByUrl = [];
    foreach ($findExisting->fetchAll() as $row) {
        $existingByUrl[$row['url']] = (int) $row['id'];
    }

    $insert = $pdo->prepare('INSERT INTO product_images (product_id, url, orden) VALUES (?, ?, ?)');
    $updateOrden = $pdo->prepare('UPDATE product_images SET orden = ? WHERE id = ?');

    foreach ($ordered as $i => $url) {
        if (isset($existingByUrl[$url])) {
            $updateOrden->execute([$i, $existingByUrl[$url]]);
        } else {
            $insert->execute([$productId, $url, $i]);
        }
    }

    if (!empty($ordered)) {
        $placeholders = implode(',', array_fill(0, count($ordered), '?'));
        $del = $pdo->prepare("DELETE FROM product_images WHERE product_id = ? AND url NOT IN ($placeholders)");
        $del->execute(array_merge([$productId], $ordered));
    } else {
        $pdo->prepare('DELETE FROM product_images WHERE product_id = ?')->execute([$productId]);
    }
}
