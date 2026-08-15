<?php
declare(strict_types=1);

require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../lib/Response.php';
require __DIR__ . '/../../lib/AdminSession.php';
require __DIR__ . '/../../lib/AccountDeletion.php';

// Elimina/anonimiza la cuenta de un cliente desde el panel — mismo criterio de acceso
// que la lista (solo dueño) y misma lógica de anonimización que el propio cliente usa
// para borrar su cuenta (lib/AccountDeletion.php: una sola fuente de verdad).

if ($_SERVER['REQUEST_METHOD'] !== 'POST') ds_json_error('Método no permitido', 405);
ds_require_rol(DS_ROL_DUENO);

$body = ds_read_json_body();
ds_admin_csrf_check($body['csrf_token'] ?? null);

$id = (int) ($body['id'] ?? 0);
if ($id <= 0) ds_json_error('ID requerido', 400);

$pdo = ds_get_pdo();
$stmt = $pdo->prepare('SELECT email FROM users WHERE id = ?');
$stmt->execute([$id]);
$user = $stmt->fetch();
if (!$user) ds_json_error('Cliente no encontrado', 404);

try {
    ds_delete_and_anonymize_user($pdo, $id, (string) $user['email']);
} catch (Throwable $e) {
    error_log('admin/clientes/delete.php: ' . $e->getMessage());
    ds_json_error('No se pudo eliminar el cliente', 500);
}

ds_json_success(['deleted' => true]);
