<?php
declare(strict_types=1);

require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../lib/Response.php';
require __DIR__ . '/../../lib/AdminSession.php';

// Historial de acciones del panel (tabla admin_audit_log, alimentada automáticamente por
// ds_admin_log() en cada POST admin vía ds_admin_csrf_check() — no hay nada que escribir
// aquí, esta pantalla es solo lectura). Solo dueño: mismo criterio que clientes/list.php,
// es información operativa sensible (quién hizo qué y desde qué IP).
//
// No incluye intentos de login (tabla separada login_attempts, ver RateLimit.php) — fuera
// de alcance de esta pantalla a propósito.

if ($_SERVER['REQUEST_METHOD'] !== 'GET') ds_json_error('Método no permitido', 405);
ds_require_rol(DS_ROL_DUENO);

$pdo = ds_get_pdo();

$where = [];
$params = [];

$adminId = isset($_GET['admin_id']) ? (int) $_GET['admin_id'] : 0;
if ($adminId > 0) {
    $where[] = 'a.admin_id = ?';
    $params[] = $adminId;
}

$accion = trim((string) ($_GET['accion'] ?? ''));
if ($accion !== '') {
    $where[] = 'a.accion = ?';
    $params[] = $accion;
}

$desde = trim((string) ($_GET['desde'] ?? ''));
if ($desde !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
    $where[] = 'a.created_at >= ?';
    $params[] = $desde . ' 00:00:00';
}

$hasta = trim((string) ($_GET['hasta'] ?? ''));
if ($hasta !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
    $where[] = 'a.created_at <= ?';
    $params[] = $hasta . ' 23:59:59';
}

$sql = 'SELECT a.id, a.admin_id, ad.nombre AS admin_nombre, ad.email AS admin_email,
               a.accion, a.detalle, a.ip, a.created_at
        FROM admin_audit_log a
        LEFT JOIN admins ad ON ad.id = a.admin_id';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY a.id DESC LIMIT 300';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$data = array_map(static fn($r) => [
    'id'           => (int) $r['id'],
    'admin_id'     => $r['admin_id'] !== null ? (int) $r['admin_id'] : null,
    'admin_nombre' => $r['admin_nombre'],
    'admin_email'  => $r['admin_email'],
    'accion'       => $r['accion'],
    'detalle'      => $r['detalle'],
    'ip'           => $r['ip'],
    'created_at'   => $r['created_at'],
], $stmt->fetchAll());

// accion no es un catálogo fijo (mezcla rutas crudas y nombres explícitos) — se ofrece al
// front la lista de valores YA usados en vez de un enum hardcodeado.
$acciones = array_column(
    $pdo->query('SELECT DISTINCT accion FROM admin_audit_log ORDER BY accion ASC')->fetchAll(),
    'accion'
);

$admins = array_map(static fn($r) => [
    'id'     => (int) $r['id'],
    'nombre' => $r['nombre'],
    'email'  => $r['email'],
], $pdo->query('SELECT id, nombre, email FROM admins ORDER BY nombre ASC')->fetchAll());

ds_json_success([
    'rows'     => $data,
    'acciones' => $acciones,
    'admins'   => $admins,
]);
