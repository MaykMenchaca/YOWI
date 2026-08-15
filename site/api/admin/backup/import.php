<?php
// Restaurar un respaldo (JSON generado por backup/export.php). SIEMPRE calcula primero
// un resumen (crear/actualizar/omitir) por PK o clave natural; solo escribe en BD si
// preview=false (confirmación explícita). A diferencia del importador de CSV
// (site/api/admin/products/import.php), esto NO es un diff campo por campo: es una
// restauración fiel del respaldo, por eso SÍ sobrescribe con lo que trae el JSON
// (el admin ya vio la vista previa y decidió aplicar).

declare(strict_types=1);

require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../lib/Response.php';
require __DIR__ . '/../../lib/AdminSession.php';
require __DIR__ . '/../../lib/Validate.php';
require __DIR__ . '/../../lib/Settings.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') ds_json_error('Método no permitido', 405);

ds_require_rol(DS_ROL_DUENO);
$body = ds_read_json_body();
// ds_admin_csrf_check ya registra auditoría automática (acción = ruta, sin detalle);
// más abajo, al terminar, registramos una entrada adicional con el detalle (tipo/preview).
ds_admin_csrf_check($body['csrf_token'] ?? null);

const DS_BACKUP_TIPOS = ['productos', 'categorias', 'marcas', 'pedidos', 'promociones', 'nosotros', 'todo'];
const DS_BACKUP_MAX_FILAS = 5000; // límite defensivo por sección (un respaldo real cabe muy por debajo)
const DS_BACKUP_ORDEN_TODO = ['categorias', 'marcas', 'productos', 'promociones', 'nosotros', 'pedidos'];
// Orden de dependencias para tipo=todo: categorías antes que productos (FK category_id);
// pedidos al final (referencian productos, aunque la FK es ON DELETE SET NULL).

// Misma lista blanca que el resto del sistema (lib/Settings.php): evita que el restore inyecte
// claves arbitrarias en la tabla settings. Un respaldo viejo con claves ya retiradas se restaura
// sin error: las claves desconocidas simplemente se omiten.
define('DS_BACKUP_SETTINGS_PERMITIDAS', ds_settings_claves());

$tipo = isset($body['tipo']) ? trim((string) $body['tipo']) : '';
if (!in_array($tipo, DS_BACKUP_TIPOS, true)) {
    ds_json_error(
        'Tipo de respaldo no reconocido: "' . $tipo . '". Valores válidos: ' . implode(', ', DS_BACKUP_TIPOS) . '.',
        400
    );
}

// preview: true|false, default true por seguridad (aplicar de verdad exige preview:false explícito).
$preview = ($body['preview'] ?? true) !== false;

if (!array_key_exists('datos', $body)) ds_json_error('Falta "datos" en el body', 400);
$datosRaw = $body['datos'];
if (!is_array($datosRaw)) {
    ds_json_error('"datos" debe ser un arreglo (o, para tipo=todo, un objeto con las 6 secciones)', 400);
}

function ds_slugify(string $s): string
{
    return trim((string) preg_replace(
        '/[^a-z0-9]+/', '-',
        strtolower((string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s))
    ), '-');
}

// ── Categorías (clave: id o slug) ───────────────────────────────────────────────
function ds_restore_categorias(PDO $pdo, array $rows, bool $apply): array
{
    $crear = 0; $actualizar = 0; $omitidos = 0; $detalle = [];
    $findId   = $pdo->prepare('SELECT id FROM categories WHERE id = ? LIMIT 1');
    $findSlug = $pdo->prepare('SELECT id FROM categories WHERE slug = ? LIMIT 1');
    $ins = $pdo->prepare('INSERT INTO categories (id, nombre, slug, orden, created_at) VALUES (?, ?, ?, ?, ?)');
    $upd = $pdo->prepare('UPDATE categories SET nombre = ?, slug = ?, orden = ? WHERE id = ?');

    foreach ($rows as $i => $row) {
        $fila = $i + 1;
        if (!is_array($row)) { $omitidos++; $detalle[] = ['fila' => $fila, 'accion' => 'omitido', 'motivo' => 'Fila inválida']; continue; }

        $nombre = ds_clean_string((string) ($row['nombre'] ?? ''), 100);
        if ($nombre === '') { $omitidos++; $detalle[] = ['fila' => $fila, 'accion' => 'omitido', 'motivo' => 'Falta el nombre']; continue; }

        $slug = ds_clean_string((string) ($row['slug'] ?? ''), 110);
        if ($slug === '') $slug = ds_slugify($nombre);
        if ($slug === '') { $omitidos++; $detalle[] = ['fila' => $fila, 'accion' => 'omitido', 'motivo' => 'Slug inválido']; continue; }

        $orden     = (int) ($row['orden'] ?? 0);
        $rowId     = isset($row['id']) && is_numeric($row['id']) ? (int) $row['id'] : null;
        $createdAt = !empty($row['created_at']) ? (string) $row['created_at'] : date('Y-m-d H:i:s');

        $existingId = 0;
        if ($rowId !== null) {
            $findId->execute([$rowId]);
            $existingId = (int) ($findId->fetchColumn() ?: 0);
        }
        if ($existingId === 0) {
            $findSlug->execute([$slug]);
            $existingId = (int) ($findSlug->fetchColumn() ?: 0);
        }

        if ($existingId > 0) {
            $actualizar++;
            $detalle[] = ['fila' => $fila, 'accion' => 'actualizar', 'id' => $existingId];
            if ($apply) $upd->execute([$nombre, $slug, $orden, $existingId]);
        } else {
            $crear++;
            $detalle[] = ['fila' => $fila, 'accion' => 'crear', 'id' => $rowId];
            if ($apply) {
                try {
                    $ins->execute([$rowId, $nombre, $slug, $orden, $createdAt]);
                } catch (\Throwable $e) {
                    // Colisión concurrente de slug/id: reintenta como actualización.
                    $crear--;
                    $findSlug->execute([$slug]);
                    $existingId = (int) ($findSlug->fetchColumn() ?: 0);
                    if ($existingId > 0) {
                        $actualizar++;
                        $upd->execute([$nombre, $slug, $orden, $existingId]);
                    } else {
                        $omitidos++;
                    }
                }
            }
        }
    }
    return ['crear' => $crear, 'actualizar' => $actualizar, 'sin_cambios_o_omitidos' => $omitidos, 'detalle' => $detalle];
}

// ── Marcas (clave: id o slug) ────────────────────────────────────────────────────
function ds_restore_marcas(PDO $pdo, array $rows, bool $apply): array
{
    $crear = 0; $actualizar = 0; $omitidos = 0; $detalle = [];
    $findId   = $pdo->prepare('SELECT id FROM brands WHERE id = ? LIMIT 1');
    $findSlug = $pdo->prepare('SELECT id FROM brands WHERE slug = ? LIMIT 1');
    $ins = $pdo->prepare('INSERT INTO brands (id, nombre, slug, imagen, enlace, orden, activo, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $upd = $pdo->prepare('UPDATE brands SET nombre = ?, slug = ?, imagen = ?, enlace = ?, orden = ?, activo = ? WHERE id = ?');

    foreach ($rows as $i => $row) {
        $fila = $i + 1;
        if (!is_array($row)) { $omitidos++; $detalle[] = ['fila' => $fila, 'accion' => 'omitido', 'motivo' => 'Fila inválida']; continue; }

        $nombre = ds_clean_string((string) ($row['nombre'] ?? ''), 120);
        if ($nombre === '') { $omitidos++; $detalle[] = ['fila' => $fila, 'accion' => 'omitido', 'motivo' => 'Falta el nombre']; continue; }

        $slug = ds_clean_string((string) ($row['slug'] ?? ''), 140);
        if ($slug === '') $slug = ds_slugify($nombre);
        if ($slug === '') { $omitidos++; $detalle[] = ['fila' => $fila, 'accion' => 'omitido', 'motivo' => 'Slug inválido']; continue; }

        $imagen    = ds_clean_string((string) ($row['imagen'] ?? ''), 255) ?: null;
        $enlace    = ds_clean_url((string) ($row['enlace'] ?? ''), 500);
        $orden     = ds_to_positive_int($row['orden'] ?? 0);
        $activo    = array_key_exists('activo', $row) ? (int) (bool) $row['activo'] : 1;
        $rowId     = isset($row['id']) && is_numeric($row['id']) ? (int) $row['id'] : null;
        $createdAt = !empty($row['created_at']) ? (string) $row['created_at'] : date('Y-m-d H:i:s');

        $existingId = 0;
        if ($rowId !== null) {
            $findId->execute([$rowId]);
            $existingId = (int) ($findId->fetchColumn() ?: 0);
        }
        if ($existingId === 0) {
            $findSlug->execute([$slug]);
            $existingId = (int) ($findSlug->fetchColumn() ?: 0);
        }

        if ($existingId > 0) {
            $actualizar++;
            $detalle[] = ['fila' => $fila, 'accion' => 'actualizar', 'id' => $existingId];
            if ($apply) $upd->execute([$nombre, $slug, $imagen, $enlace, $orden, $activo, $existingId]);
        } else {
            $crear++;
            $detalle[] = ['fila' => $fila, 'accion' => 'crear', 'id' => $rowId];
            if ($apply) {
                try {
                    $ins->execute([$rowId, $nombre, $slug, $imagen, $enlace, $orden, $activo, $createdAt]);
                } catch (\Throwable $e) {
                    $crear--;
                    $findSlug->execute([$slug]);
                    $existingId = (int) ($findSlug->fetchColumn() ?: 0);
                    if ($existingId > 0) {
                        $actualizar++;
                        $upd->execute([$nombre, $slug, $imagen, $enlace, $orden, $activo, $existingId]);
                    } else {
                        $omitidos++;
                    }
                }
            }
        }
    }
    return ['crear' => $crear, 'actualizar' => $actualizar, 'sin_cambios_o_omitidos' => $omitidos, 'detalle' => $detalle];
}

// ── Promociones / banners (clave: id) ───────────────────────────────────────────
function ds_restore_promociones(PDO $pdo, array $rows, bool $apply): array
{
    $crear = 0; $actualizar = 0; $omitidos = 0; $detalle = [];
    $findId = $pdo->prepare('SELECT id FROM banners WHERE id = ? LIMIT 1');
    $ins = $pdo->prepare('INSERT INTO banners (id, titulo, imagen, enlace, orden, activo, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $upd = $pdo->prepare('UPDATE banners SET titulo = ?, imagen = ?, enlace = ?, orden = ?, activo = ? WHERE id = ?');

    foreach ($rows as $i => $row) {
        $fila = $i + 1;
        if (!is_array($row)) { $omitidos++; $detalle[] = ['fila' => $fila, 'accion' => 'omitido', 'motivo' => 'Fila inválida']; continue; }

        $imagen = ds_clean_url((string) ($row['imagen'] ?? ''), 255) ?? '';
        if ($imagen === '') { $omitidos++; $detalle[] = ['fila' => $fila, 'accion' => 'omitido', 'motivo' => 'Falta la imagen']; continue; }

        $titulo    = ds_clean_string((string) ($row['titulo'] ?? ''), 150) ?: null;
        $enlace    = ds_clean_url((string) ($row['enlace'] ?? ''), 500);
        $orden     = ds_to_positive_int($row['orden'] ?? 0);
        $activo    = array_key_exists('activo', $row) ? (int) (bool) $row['activo'] : 1;
        $rowId     = isset($row['id']) && is_numeric($row['id']) ? (int) $row['id'] : null;
        $createdAt = !empty($row['created_at']) ? (string) $row['created_at'] : date('Y-m-d H:i:s');

        $existingId = 0;
        if ($rowId !== null) {
            $findId->execute([$rowId]);
            $existingId = (int) ($findId->fetchColumn() ?: 0);
        }

        if ($existingId > 0) {
            $actualizar++;
            $detalle[] = ['fila' => $fila, 'accion' => 'actualizar', 'id' => $existingId];
            if ($apply) $upd->execute([$titulo, $imagen, $enlace, $orden, $activo, $existingId]);
        } else {
            $crear++;
            $idxDetalle = count($detalle);
            $detalle[] = ['fila' => $fila, 'accion' => 'crear', 'id' => $rowId];
            if ($apply) {
                try {
                    $ins->execute([$rowId, $titulo, $imagen, $enlace, $orden, $activo, $createdAt]);
                } catch (\Throwable $e) {
                    $crear--;
                    $omitidos++;
                    $detalle[$idxDetalle] = ['fila' => $fila, 'accion' => 'omitido', 'motivo' => 'Error al guardar la fila'];
                }
            }
        }
    }
    return ['crear' => $crear, 'actualizar' => $actualizar, 'sin_cambios_o_omitidos' => $omitidos, 'detalle' => $detalle];
}

// ── Nosotros / settings (clave: clave, whitelist de claves permitidas) ──────────
function ds_restore_nosotros(PDO $pdo, array $rows, bool $apply): array
{
    $crear = 0; $actualizar = 0; $omitidos = 0; $detalle = [];
    $find = $pdo->prepare('SELECT clave FROM settings WHERE clave = ? LIMIT 1');
    $ins  = $pdo->prepare('INSERT INTO settings (clave, valor) VALUES (?, ?)');
    $upd  = $pdo->prepare('UPDATE settings SET valor = ? WHERE clave = ?');

    foreach ($rows as $i => $row) {
        $fila = $i + 1;
        if (!is_array($row)) { $omitidos++; $detalle[] = ['fila' => $fila, 'accion' => 'omitido', 'motivo' => 'Fila inválida']; continue; }

        $clave = ds_clean_string((string) ($row['clave'] ?? ''), 60);
        if ($clave === '') { $omitidos++; $detalle[] = ['fila' => $fila, 'accion' => 'omitido', 'motivo' => 'Falta la clave']; continue; }
        if (!in_array($clave, DS_BACKUP_SETTINGS_PERMITIDAS, true)) {
            $omitidos++;
            $detalle[] = ['fila' => $fila, 'clave' => $clave, 'accion' => 'omitido', 'motivo' => 'Clave no reconocida'];
            continue;
        }

        $valor = ds_clean_string((string) ($row['valor'] ?? ''), 5000);
        $find->execute([$clave]);
        $exists = (bool) $find->fetchColumn();

        if ($exists) {
            $actualizar++;
            $detalle[] = ['fila' => $fila, 'clave' => $clave, 'accion' => 'actualizar'];
            if ($apply) $upd->execute([$valor, $clave]);
        } else {
            $crear++;
            $detalle[] = ['fila' => $fila, 'clave' => $clave, 'accion' => 'crear'];
            if ($apply) $ins->execute([$clave, $valor]);
        }
    }
    return ['crear' => $crear, 'actualizar' => $actualizar, 'sin_cambios_o_omitidos' => $omitidos, 'detalle' => $detalle];
}

// ── Productos (clave: id, o sku si trae uno y existe) ────────────────────────────
// $categoriaIdsExtra: ids de categoría presentes en la sección "categorias" del MISMO
// restore de tipo=todo (aunque aún no existan en BD, se van a crear en este mismo run).
function ds_restore_productos(PDO $pdo, array $rows, bool $apply, array $categoriaIdsExtra = []): array
{
    $crear = 0; $actualizar = 0; $omitidos = 0; $detalle = [];
    $findId  = $pdo->prepare('SELECT id FROM products WHERE id = ? LIMIT 1');
    $findSku = $pdo->prepare('SELECT id FROM products WHERE sku = ? LIMIT 1');
    $findCat = $pdo->prepare('SELECT id FROM categories WHERE id = ? LIMIT 1');
    $ins = $pdo->prepare(
        'INSERT INTO products (id, sku, nombre, marca, category_id, cantidad, unidad, descripcion, precio, precio_original, stock, imagen, badge, destacado, activo, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $upd = $pdo->prepare(
        'UPDATE products SET sku = ?, nombre = ?, marca = ?, category_id = ?, cantidad = ?, unidad = ?, descripcion = ?,
                              precio = ?, precio_original = ?, stock = ?, imagen = ?, badge = ?, destacado = ?, activo = ?
         WHERE id = ?'
    );

    foreach ($rows as $i => $row) {
        $fila = $i + 1;
        if (!is_array($row)) { $omitidos++; $detalle[] = ['fila' => $fila, 'accion' => 'omitido', 'motivo' => 'Fila inválida']; continue; }

        $nombre = ds_clean_string((string) ($row['nombre'] ?? ''), 255);
        $marca  = ds_clean_string((string) ($row['marca'] ?? ''), 120);
        if ($nombre === '' || $marca === '') { $omitidos++; $detalle[] = ['fila' => $fila, 'accion' => 'omitido', 'motivo' => 'Falta nombre o marca']; continue; }

        $precio = is_numeric($row['precio'] ?? null) ? (float) $row['precio'] : null;
        if ($precio === null) { $omitidos++; $detalle[] = ['fila' => $fila, 'accion' => 'omitido', 'motivo' => 'Precio inválido']; continue; }

        $categoryId = isset($row['category_id']) && is_numeric($row['category_id']) ? (int) $row['category_id'] : 0;
        if ($categoryId <= 0) { $omitidos++; $detalle[] = ['fila' => $fila, 'accion' => 'omitido', 'motivo' => 'Falta category_id']; continue; }
        $findCat->execute([$categoryId]);
        $catExiste = (bool) $findCat->fetchColumn();
        if (!$catExiste && !in_array($categoryId, $categoriaIdsExtra, true)) {
            $omitidos++;
            $detalle[] = ['fila' => $fila, 'accion' => 'omitido', 'motivo' => "La categoría id=$categoryId no existe en la BD ni viene en este mismo respaldo"];
            continue;
        }

        $cantidad    = ds_clean_string((string) ($row['cantidad'] ?? ''), 80);
        $unidad      = ds_clean_string((string) ($row['unidad'] ?? ''), 20) ?: null;
        $descRaw     = (string) ($row['descripcion'] ?? '');
        $descripcion = $descRaw !== '' ? ds_clean_string($descRaw, 5000) : null;
        $precioOrig  = is_numeric($row['precio_original'] ?? null) ? (float) $row['precio_original'] : null;
        $stock       = (array_key_exists('stock', $row) && $row['stock'] !== null && $row['stock'] !== '')
                        ? max(0, (int) $row['stock']) : null;
        $imagen      = ds_clean_url((string) ($row['imagen'] ?? ''), 255) ?? 'assets/img/producto-placeholder.svg';
        $badge       = ds_clean_string((string) ($row['badge'] ?? ''), 30) ?: null;
        $destacado   = array_key_exists('destacado', $row) ? (int) (bool) $row['destacado'] : 0;
        $activo      = array_key_exists('activo', $row) ? (int) (bool) $row['activo'] : 1;
        $skuRaw      = ds_clean_string((string) ($row['sku'] ?? ''), 64);
        $sku         = $skuRaw !== '' ? $skuRaw : null;
        $rowId       = isset($row['id']) && is_numeric($row['id']) ? (int) $row['id'] : null;
        $createdAt   = !empty($row['created_at']) ? (string) $row['created_at'] : date('Y-m-d H:i:s');
        $updatedAt   = !empty($row['updated_at']) ? (string) $row['updated_at'] : date('Y-m-d H:i:s');

        // Emparejamiento: primero por id (si el JSON trae uno y coincide con uno en BD),
        // luego por SKU (si trae uno y existe). Si ninguno matchea, se crea nuevo.
        $existingId = 0;
        if ($rowId !== null) {
            $findId->execute([$rowId]);
            $existingId = (int) ($findId->fetchColumn() ?: 0);
        }
        if ($existingId === 0 && $sku !== null) {
            $findSku->execute([$sku]);
            $existingId = (int) ($findSku->fetchColumn() ?: 0);
        }

        if ($existingId > 0) {
            $actualizar++;
            $detalle[] = ['fila' => $fila, 'accion' => 'actualizar', 'id' => $existingId];
            if ($apply) {
                $upd->execute([$sku, $nombre, $marca, $categoryId, $cantidad, $unidad, $descripcion, $precio, $precioOrig, $stock, $imagen, $badge, $destacado, $activo, $existingId]);
            }
        } else {
            $crear++;
            $idxDetalle = count($detalle);
            $detalle[] = ['fila' => $fila, 'accion' => 'crear', 'id' => $rowId];
            if ($apply) {
                try {
                    $ins->execute([$rowId, $sku, $nombre, $marca, $categoryId, $cantidad, $unidad, $descripcion, $precio, $precioOrig, $stock, $imagen, $badge, $destacado, $activo, $createdAt, $updatedAt]);
                } catch (\Throwable $e) {
                    $isDupSku = $e instanceof \PDOException && ((int) $e->getCode() === 23000 || ($e->errorInfo[1] ?? null) === 1062);
                    $crear--;
                    $omitidos++;
                    $detalle[$idxDetalle] = ['fila' => $fila, 'accion' => 'omitido', 'motivo' => $isDupSku ? "El SKU '$sku' ya pertenece a otro producto" : 'Error al guardar la fila'];
                }
            }
        }
    }
    return ['crear' => $crear, 'actualizar' => $actualizar, 'sin_cambios_o_omitidos' => $omitidos, 'detalle' => $detalle];
}

// ── Pedidos (clave: id; si ya existe se OMITE siempre — nunca se duplican) ──────
function ds_restore_pedidos(PDO $pdo, array $rows, bool $apply): array
{
    $crear = 0; $actualizar = 0; $omitidos = 0; $detalle = [];
    $estadosValidos = ['pendiente', 'confirmado', 'enviado', 'entregado', 'cancelado'];
    $findOrder = $pdo->prepare('SELECT id FROM orders WHERE id = ? LIMIT 1');
    $findUser  = $pdo->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
    $findProd  = $pdo->prepare('SELECT id FROM products WHERE id = ? LIMIT 1');
    $insOrder = $pdo->prepare(
        'INSERT INTO orders (id, user_id, nombre_cliente, ciudad, telefono, direccion_envio, total, estado, mensaje_whatsapp, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $insItem = $pdo->prepare(
        'INSERT INTO order_items (order_id, producto_id, nombre_producto, precio_unitario, cantidad, subtotal)
         VALUES (?, ?, ?, ?, ?, ?)'
    );

    foreach ($rows as $i => $row) {
        $fila = $i + 1;
        if (!is_array($row)) { $omitidos++; $detalle[] = ['fila' => $fila, 'accion' => 'omitido', 'motivo' => 'Fila inválida']; continue; }

        $rowId = isset($row['id']) && is_numeric($row['id']) ? (int) $row['id'] : null;
        if ($rowId === null) { $omitidos++; $detalle[] = ['fila' => $fila, 'accion' => 'omitido', 'motivo' => 'Falta el id del pedido']; continue; }

        $findOrder->execute([$rowId]);
        if ((bool) $findOrder->fetchColumn()) {
            // Nunca se duplican pedidos: si el id ya existe, se omite tal cual (ni se actualiza).
            $omitidos++;
            $detalle[] = ['fila' => $fila, 'id' => $rowId, 'accion' => 'omitido', 'motivo' => 'ya existe'];
            continue;
        }

        $nombreCliente = ds_clean_string((string) ($row['nombre_cliente'] ?? ''), 150);
        if ($nombreCliente === '') { $omitidos++; $detalle[] = ['fila' => $fila, 'accion' => 'omitido', 'motivo' => 'Falta el nombre del cliente']; continue; }

        $ciudad    = ds_clean_string((string) ($row['ciudad'] ?? ''), 120) ?: null;
        $telefono  = ds_clean_string((string) ($row['telefono'] ?? ''), 30) ?: null;
        $direccion = ds_clean_string((string) ($row['direccion_envio'] ?? ''), 5000) ?: null;
        // El total y los precios del pedido se toman TAL CUAL del respaldo (no se recalculan):
        // esto es una restauración fiel de un registro histórico, no un pedido nuevo del cliente
        // (la regla "el precio siempre lo recalcula el servidor" aplica al checkout, no aquí).
        $total   = is_numeric($row['total'] ?? null) ? round((float) $row['total'], 2) : 0.0;
        $estado  = in_array($row['estado'] ?? '', $estadosValidos, true) ? (string) $row['estado'] : 'pendiente';
        $mensaje = ds_clean_string((string) ($row['mensaje_whatsapp'] ?? ''), 4000) ?: null;

        $userId = isset($row['user_id']) && is_numeric($row['user_id']) ? (int) $row['user_id'] : null;
        if ($userId !== null) {
            $findUser->execute([$userId]);
            if (!$findUser->fetchColumn()) $userId = null; // usuario ya no existe: no violar la FK
        }

        $createdAt = !empty($row['created_at']) ? (string) $row['created_at'] : date('Y-m-d H:i:s');
        $items = is_array($row['items'] ?? null) ? $row['items'] : [];

        $crear++;
        $detalle[] = ['fila' => $fila, 'id' => $rowId, 'accion' => 'crear', 'items' => count($items)];

        if ($apply) {
            $insOrder->execute([$rowId, $userId, $nombreCliente, $ciudad, $telefono, $direccion, $total, $estado, $mensaje, $createdAt]);
            foreach ($items as $item) {
                if (!is_array($item)) continue;
                $prodId = isset($item['producto_id']) && is_numeric($item['producto_id']) ? (int) $item['producto_id'] : null;
                if ($prodId !== null) {
                    $findProd->execute([$prodId]);
                    if (!$findProd->fetchColumn()) $prodId = null; // producto ya no existe: no violar la FK
                }
                $nombreProd = ds_clean_string((string) ($item['nombre_producto'] ?? ''), 255);
                $precioUnit = is_numeric($item['precio_unitario'] ?? null) ? (float) $item['precio_unitario'] : 0.0;
                $cantidad   = is_numeric($item['cantidad'] ?? null) ? max(1, (int) $item['cantidad']) : 1;
                $subtotal   = is_numeric($item['subtotal'] ?? null) ? (float) $item['subtotal'] : round($precioUnit * $cantidad, 2);
                $insItem->execute([$rowId, $prodId, $nombreProd, $precioUnit, $cantidad, $subtotal]);
            }
        }
    }
    return ['crear' => $crear, 'actualizar' => $actualizar, 'sin_cambios_o_omitidos' => $omitidos, 'detalle' => $detalle];
}

function ds_restore_dispatch(PDO $pdo, string $tipo, array $rows, bool $apply, array $categoriaIdsExtra): array
{
    switch ($tipo) {
        case 'categorias':  return ds_restore_categorias($pdo, $rows, $apply);
        case 'marcas':      return ds_restore_marcas($pdo, $rows, $apply);
        case 'productos':   return ds_restore_productos($pdo, $rows, $apply, $categoriaIdsExtra);
        case 'promociones': return ds_restore_promociones($pdo, $rows, $apply);
        case 'nosotros':    return ds_restore_nosotros($pdo, $rows, $apply);
        case 'pedidos':     return ds_restore_pedidos($pdo, $rows, $apply);
    }
    return ['crear' => 0, 'actualizar' => 0, 'sin_cambios_o_omitidos' => 0, 'detalle' => []];
}

// ── Validación de tamaño (defensivo) ─────────────────────────────────────────────
function ds_backup_check_max(array $rows): void
{
    if (count($rows) > DS_BACKUP_MAX_FILAS) {
        ds_json_error('El respaldo trae más de ' . DS_BACKUP_MAX_FILAS . ' filas en una sección; divídelo o contacta soporte', 400);
    }
}

if ($tipo === 'todo') {
    foreach (DS_BACKUP_ORDEN_TODO as $sub) {
        if (isset($datosRaw[$sub]) && is_array($datosRaw[$sub])) ds_backup_check_max($datosRaw[$sub]);
    }
} else {
    ds_backup_check_max($datosRaw);
}

$pdo = ds_get_pdo();

try {
    if (!$preview) $pdo->beginTransaction();

    if ($tipo === 'todo') {
        $secciones = [];
        $categoriaIdsExtra = [];
        if (isset($datosRaw['categorias']) && is_array($datosRaw['categorias'])) {
            foreach ($datosRaw['categorias'] as $r) {
                if (is_array($r) && isset($r['id']) && is_numeric($r['id'])) $categoriaIdsExtra[] = (int) $r['id'];
            }
        }
        foreach (DS_BACKUP_ORDEN_TODO as $sub) {
            if (!isset($datosRaw[$sub]) || !is_array($datosRaw[$sub])) continue; // sección ausente: se salta sin error
            $secciones[$sub] = ds_restore_dispatch($pdo, $sub, $datosRaw[$sub], !$preview, $categoriaIdsExtra);
        }
        $totales = ['crear' => 0, 'actualizar' => 0, 'sin_cambios_o_omitidos' => 0];
        foreach ($secciones as $r) {
            $totales['crear'] += $r['crear'];
            $totales['actualizar'] += $r['actualizar'];
            $totales['sin_cambios_o_omitidos'] += $r['sin_cambios_o_omitidos'];
        }
        $resumen = $totales + ['secciones' => $secciones];
    } else {
        $resumen = ds_restore_dispatch($pdo, $tipo, $datosRaw, !$preview, []);
    }

    if (!$preview) $pdo->commit();
} catch (\Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('backup/import.php: ' . $e->getMessage());
    ds_json_error('No se pudo restaurar el respaldo', 500);
}

ds_admin_log('backup/import', 'tipo=' . $tipo . ' preview=' . ($preview ? '1' : '0'));

ds_json_success([
    'tipo'    => $tipo,
    'preview' => $preview,
    'resumen' => $resumen,
]);
