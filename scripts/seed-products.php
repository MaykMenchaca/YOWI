<?php
// Importa productos desde un JSON a MySQL.
// Uso: php scripts/seed-products.php [ruta-al-json]
// Por defecto lee site/assets/data/productos-demo.json
// Campos requeridos por producto: nombre, marca, categoria
// Campos opcionales: cantidad, descripcion, precio, stock, imagen, badge, destacado

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    echo "Solo ejecutable desde CLI.\n";
    exit(1);
}

$path = $argv[1] ?? __DIR__ . '/../site/assets/data/productos-demo.json';

if (!file_exists($path)) {
    echo "Archivo no encontrado: $path\n";
    exit(1);
}

$raw = file_get_contents($path);
$products = json_decode($raw, true);

if (!is_array($products) || empty($products)) {
    echo "JSON vacío o inválido.\n";
    exit(1);
}

require __DIR__ . '/../site/api/config/database.php';

$pdo = ds_get_pdo();

$insCateg = $pdo->prepare(
    'INSERT IGNORE INTO categories (nombre, slug, orden) VALUES (?, ?, 0)'
);

$insProd = $pdo->prepare(
    'INSERT INTO products (nombre, marca, category_id, cantidad, descripcion, precio, precio_original, stock, imagen, badge, destacado)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);

$getCateg = $pdo->prepare('SELECT id FROM categories WHERE slug = ?');

$imported = 0;
$errors   = 0;

foreach ($products as $i => $p) {
    $catNombre = trim((string)($p['categoria'] ?? ''));
    if ($catNombre === '') {
        echo "  [SKIP] producto #$i sin categoría.\n";
        $errors++;
        continue;
    }

    // Slug de categoría: minúsculas, sin acentos, guiones
    $slug = trim((string) preg_replace(
        '/[^a-z0-9]+/', '-',
        strtolower((string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $catNombre))
    ), '-');

    $insCateg->execute([$catNombre, $slug]);
    $getCateg->execute([$slug]);
    $catRow = $getCateg->fetch();
    if (!$catRow) {
        echo "  [ERROR] no se pudo obtener categoría '$catNombre'.\n";
        $errors++;
        continue;
    }

    $nombre    = mb_substr(trim((string)($p['nombre'] ?? '')), 0, 255);
    $marca     = mb_substr(trim((string)($p['marca'] ?? '')), 0, 120);
    if ($nombre === '' || $marca === '') {
        echo "  [SKIP] producto #$i sin nombre o marca.\n";
        $errors++;
        continue;
    }

    $insProd->execute([
        $nombre,
        $marca,
        (int) $catRow['id'],
        mb_substr(trim((string)($p['cantidad'] ?? '')), 0, 80),
        trim((string)($p['descripcion'] ?? '')) ?: null,
        max(0.0, (float)($p['precio'] ?? 0)),
        isset($p['precio_original']) ? max(0.0, (float)$p['precio_original']) : null,
        max(0, (int)($p['stock'] ?? 0)),
        mb_substr(trim((string)($p['imagen'] ?? 'assets/img/producto-placeholder.svg')), 0, 255),
        isset($p['badge']) ? mb_substr(trim((string)$p['badge']), 0, 30) : null,
        !empty($p['destacado']) ? 1 : 0,
    ]);

    $imported++;
}

echo "Importación completada: $imported productos, $errors omitidos.\n";
