<?php
declare(strict_types=1);

require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../lib/Response.php';
require __DIR__ . '/../../lib/AdminSession.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') ds_json_error('Método no permitido', 405);
ds_require_rol(DS_ROL_OPERADOR);

$pdo = ds_get_pdo();

// Mismo orden de columnas que la plantilla del importador (site/assets/js/admin/products.js)
// y que el propio import.php acepta, para que el CSV exportado se pueda reimportar tal cual.
$COLS = ['sku', 'nombre', 'marca', 'categoria', 'cantidad', 'unidad', 'descripcion', 'precio', 'precio_original', 'stock', 'imagen', 'imagenes', 'badge', 'sabores', 'destacado', 'activo'];

// sabores/imagenes se arman con GROUP_CONCAT en el mismo formato "nombre:stock:precio|..."
// y "url|url|..." que acepta el importador (F3.2/F4.2) — así "exportar y reimportar tal
// cual" también conserva sabores y galería, no solo los campos base del producto.
$stmt = $pdo->query(
    "SELECT p.sku, p.nombre, p.marca, c.nombre AS categoria, p.cantidad, p.unidad, p.descripcion,
            p.precio, p.precio_original, p.stock, p.imagen, p.badge, p.destacado, p.activo,
            (SELECT GROUP_CONCAT(CONCAT(f.nombre, ':', COALESCE(f.stock, ''), ':', COALESCE(f.precio, ''))
                                  ORDER BY f.orden SEPARATOR '|')
             FROM product_flavors f WHERE f.product_id = p.id) AS sabores,
            (SELECT GROUP_CONCAT(i.url ORDER BY i.orden SEPARATOR '|')
             FROM product_images i WHERE i.product_id = p.id) AS imagenes
     FROM products p
     JOIN categories c ON c.id = p.category_id
     ORDER BY p.id ASC"
);

// Mitigación estándar de CSV injection: si una celda empieza con un carácter que Excel/
// LibreOffice interpretan como inicio de fórmula (= + - @ tab CR), se le antepone un
// apóstrofo. Excel lo trata como "forzar texto" y no lo muestra; así una celda maliciosa
// (p. ej. un nombre de producto "=cmd|'/C calc'!A1") no se ejecuta al abrir el CSV. Único
// costo: si ese mismo CSV se reimporta tal cual, esa celda concreta reimporta con el
// apóstrofo incluido — trade-off estándar (mismo que usa Google Sheets), y es un caso
// borde (nombre de producto empezando literalmente con esos símbolos).
function ds_csv_safe_cell($v): string
{
    $s = (string) $v;
    if ($s !== '' && strpbrk($s[0], "=+-@\t\r") !== false) {
        return "'" . $s;
    }
    return $s;
}

$filename = 'catalogo-' . date('Y-m-d') . '.csv';

http_response_code(200);
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store');

$out = fopen('php://output', 'w');
// BOM UTF-8 para que Excel abra los acentos correctamente (el importador lo quita al leer).
fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, $COLS, ',', '"', '\\');

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($out, [
        ds_csv_safe_cell($row['sku'] ?? ''),
        ds_csv_safe_cell($row['nombre']),
        ds_csv_safe_cell($row['marca']),
        ds_csv_safe_cell($row['categoria']),
        ds_csv_safe_cell($row['cantidad']),
        ds_csv_safe_cell($row['unidad'] ?? ''),
        ds_csv_safe_cell($row['descripcion'] ?? ''),
        $row['precio'],
        $row['precio_original'] ?? '',
        $row['stock'] ?? '',
        ds_csv_safe_cell($row['imagen']),
        ds_csv_safe_cell($row['imagenes'] ?? ''),
        ds_csv_safe_cell($row['badge'] ?? ''),
        ds_csv_safe_cell($row['sabores'] ?? ''),
        ((int) $row['destacado']) === 1 ? '1' : '0',
        ((int) $row['activo']) === 1 ? '1' : '0',
    ], ',', '"', '\\');
}

fclose($out);
exit;
