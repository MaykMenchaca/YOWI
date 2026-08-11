<?php
declare(strict_types=1);

require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../lib/Response.php';
require __DIR__ . '/../../lib/AdminSession.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') ds_json_error('Método no permitido', 405);

// DUENO y no LECTURA (a diferencia de list.php/get.php): este CSV es un volcado masivo de
// datos personales de clientes (nombre, teléfono, dirección) en un solo archivo descargable,
// mismo criterio que backup/export.php para 'pedidos'.
ds_require_rol(DS_ROL_DUENO);

$COLS = ['Pedido #', 'Fecha', 'Cliente', 'Teléfono', 'Ciudad', 'Dirección', 'Email cuenta', 'Artículos', 'Total', 'Estado'];

$validEstados = ['pendiente', 'confirmado', 'enviado', 'entregado', 'cancelado'];
$estado = isset($_GET['estado']) ? trim((string) $_GET['estado']) : '';

$where  = [];
$params = [];
if ($estado !== '' && in_array($estado, $validEstados, true)) {
    $where[]  = 'o.estado = ?';
    $params[] = $estado;
}
$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$pdo = ds_get_pdo();

$stmt = $pdo->prepare(
    "SELECT o.id, o.nombre_cliente, o.ciudad, o.telefono, o.direccion_envio, o.total, o.estado, o.created_at,
            u.email AS user_email,
            (SELECT GROUP_CONCAT(CONCAT(oi.nombre_producto, ' × ', oi.cantidad) SEPARATOR '; ')
             FROM order_items oi WHERE oi.order_id = o.id) AS articulos
     FROM orders o
     LEFT JOIN users u ON u.id = o.user_id
     $whereClause
     ORDER BY o.id ASC"
);
$stmt->execute($params);

// Mitigación estándar de CSV injection (misma que products/export.php): si una celda
// empieza con un carácter que Excel/LibreOffice interpretan como inicio de fórmula
// (= + - @ tab CR), se le antepone un apóstrofo.
function ds_csv_safe_cell($v): string
{
    $s = (string) $v;
    if ($s !== '' && strpbrk($s[0], "=+-@\t\r") !== false) {
        return "'" . $s;
    }
    return $s;
}

$filename = 'pedidos-' . date('Y-m-d') . '.csv';

http_response_code(200);
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store');

$out = fopen('php://output', 'w');
// BOM UTF-8 para que Excel abra los acentos correctamente.
fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, $COLS, ',', '"', '\\');

$count = 0;
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $count++;
    $fecha = date('Y-m-d H:i', strtotime((string) $row['created_at']));
    fputcsv($out, [
        (int) $row['id'],
        $fecha,
        ds_csv_safe_cell($row['nombre_cliente']),
        ds_csv_safe_cell($row['telefono']),
        ds_csv_safe_cell($row['ciudad']),
        ds_csv_safe_cell($row['direccion_envio']),
        ds_csv_safe_cell($row['user_email'] ?? ''),
        ds_csv_safe_cell($row['articulos'] ?? ''),
        $row['total'],
        ds_csv_safe_cell($row['estado']),
    ], ',', '"', '\\');
}

fclose($out);

ds_admin_log('orders/export', 'exportó ' . $count . ' pedidos' . ($estado !== '' && in_array($estado, $validEstados, true) ? ' (estado=' . $estado . ')' : ''));

exit;
