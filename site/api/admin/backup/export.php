<?php
// Respaldo descargable por apartado (JSON). Solo lectura, pero expone datos sensibles
// del negocio (catálogo completo, pedidos, etc.), por eso exige sesión admin igual que
// cualquier endpoint de escritura. No usa ds_json_success/error porque la respuesta debe
// forzar la descarga de un archivo en el navegador, no un JSON de API normal.

declare(strict_types=1);

require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../lib/Response.php';
require __DIR__ . '/../../lib/AdminSession.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') ds_json_error('Método no permitido', 405);

ds_require_rol(DS_ROL_DUENO);

const DS_BACKUP_TIPOS = ['productos', 'categorias', 'marcas', 'pedidos', 'promociones', 'nosotros', 'todo'];
const DS_BACKUP_VERSION = 1;

$tipo = isset($_GET['tipo']) ? trim((string) $_GET['tipo']) : 'todo';
if ($tipo === '') $tipo = 'todo';
if (!in_array($tipo, DS_BACKUP_TIPOS, true)) {
    ds_json_error(
        'Tipo de respaldo no reconocido: "' . $tipo . '". Valores válidos: ' . implode(', ', DS_BACKUP_TIPOS) . '.',
        400
    );
}

$pdo = ds_get_pdo();

function ds_backup_productos(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT id, sku, nombre, marca, category_id, cantidad, unidad, descripcion,
                precio, precio_original, stock, imagen, badge, destacado, activo,
                created_at, updated_at
         FROM products
         ORDER BY id'
    );
    return $stmt->fetchAll();
}

function ds_backup_categorias(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT id, nombre, slug, orden, created_at FROM categories ORDER BY id');
    return $stmt->fetchAll();
}

function ds_backup_marcas(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT id, nombre, slug, imagen, enlace, orden, activo, created_at, updated_at
         FROM brands
         ORDER BY id'
    );
    return $stmt->fetchAll();
}

function ds_backup_promociones(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT id, titulo, imagen, enlace, orden, activo, created_at, updated_at
         FROM banners
         ORDER BY id'
    );
    return $stmt->fetchAll();
}

function ds_backup_nosotros(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT clave, valor, updated_at FROM settings ORDER BY clave');
    return $stmt->fetchAll();
}

function ds_backup_pedidos(PDO $pdo): array
{
    $orders = $pdo->query(
        'SELECT id, user_id, nombre_cliente, ciudad, telefono, direccion_envio, total,
                estado, mensaje_whatsapp, created_at
         FROM orders
         ORDER BY id'
    )->fetchAll();

    $itemsStmt = $pdo->prepare(
        'SELECT id, order_id, producto_id, nombre_producto, precio_unitario, cantidad, subtotal
         FROM order_items
         WHERE order_id = ?
         ORDER BY id'
    );

    foreach ($orders as &$order) {
        $itemsStmt->execute([$order['id']]);
        $order['items'] = $itemsStmt->fetchAll();
    }
    unset($order);

    return $orders;
}

switch ($tipo) {
    case 'productos':
        $datos = ds_backup_productos($pdo);
        break;
    case 'categorias':
        $datos = ds_backup_categorias($pdo);
        break;
    case 'marcas':
        $datos = ds_backup_marcas($pdo);
        break;
    case 'pedidos':
        $datos = ds_backup_pedidos($pdo);
        break;
    case 'promociones':
        $datos = ds_backup_promociones($pdo);
        break;
    case 'nosotros':
        $datos = ds_backup_nosotros($pdo);
        break;
    case 'todo':
    default:
        $datos = [
            'productos'   => ds_backup_productos($pdo),
            'categorias'  => ds_backup_categorias($pdo),
            'marcas'      => ds_backup_marcas($pdo),
            'pedidos'     => ds_backup_pedidos($pdo),
            'promociones' => ds_backup_promociones($pdo),
            'nosotros'    => ds_backup_nosotros($pdo),
        ];
        break;
}

ds_admin_log('backup/export', 'tipo=' . $tipo);

$generadoEn = date('Y-m-d H:i:s');
$payload = [
    'tipo'         => $tipo,
    'generado_en'  => $generadoEn,
    'version'      => DS_BACKUP_VERSION,
    'datos'        => $datos,
];

$filename = 'respaldo-' . $tipo . '-' . date('Y-m-d') . '.json';

header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
exit;
