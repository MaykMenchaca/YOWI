<?php
declare(strict_types=1);

require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../lib/Response.php';
require __DIR__ . '/../../lib/AdminSession.php';

// Borrado real (a diferencia de la baja normal de un empleado, que es desactivar —
// admins/update.php con activo=false — y conserva su historial de auditoría). Este
// endpoint es para cuentas creadas por error. admin_recovery_codes cae en cascada
// (ON DELETE CASCADE); las filas de admin_audit_log del borrado quedan con admin_id=NULL
// (ON DELETE SET NULL) — pierden el nombre asociado, por eso la UI empuja a desactivar.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') ds_json_error('Método no permitido', 405);
$actorId = ds_require_rol(DS_ROL_DUENO);

$body = ds_read_json_body();
ds_admin_csrf_check($body['csrf_token'] ?? null);

$id = (int) ($body['id'] ?? 0);
if ($id <= 0) ds_json_error('id es requerido', 400);

// Nadie se borra a sí mismo (evita quedarte fuera del panel por accidente).
if ($id === $actorId) ds_json_error('No puedes eliminar tu propia cuenta', 403);

$pdo = ds_get_pdo();

// Misma protección transaccional que admins/update.php: el conteo de dueños y el borrado
// van en la misma transacción con FOR UPDATE, para que dos borrados/degradaciones
// concurrentes no puedan leer ambos "todavía queda el otro" y dejar el sistema en 0
// dueños activos.
$pdo->beginTransaction();
try {
    $check = $pdo->prepare('SELECT rol, activo, email FROM admins WHERE id = ? FOR UPDATE');
    $check->execute([$id]);
    $row = $check->fetch();
    if (!$row) { $pdo->rollBack(); ds_json_error('Cuenta no encontrada', 404); }

    if ($row['rol'] === DS_ROL_DUENO && (int) $row['activo'] === 1) {
        $countStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM admins WHERE rol = 'dueno' AND activo = 1 AND id != ? FOR UPDATE"
        );
        $countStmt->execute([$id]);
        if ((int) $countStmt->fetchColumn() === 0) {
            $pdo->rollBack();
            ds_json_error('Debe quedar al menos un dueño activo — no puedes eliminarlo', 409);
        }
    }

    $email = $row['email'];
    $stmt = $pdo->prepare('DELETE FROM admins WHERE id = ?');
    $stmt->execute([$id]);
    $huboBorrado = $stmt->rowCount() > 0;

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $e;
}

ds_admin_log('admins/delete', "admin_id={$id} email={$email}");

ds_json_success(['deleted' => $huboBorrado]);
