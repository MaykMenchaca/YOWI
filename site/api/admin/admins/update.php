<?php
declare(strict_types=1);

require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../lib/Response.php';
require __DIR__ . '/../../lib/AdminSession.php';
require __DIR__ . '/../../lib/Validate.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') ds_json_error('Método no permitido', 405);
$actorId = ds_require_rol(DS_ROL_DUENO);

$body = ds_read_json_body();
ds_admin_csrf_check($body['csrf_token'] ?? null);

$id     = (int) ($body['id'] ?? 0);
$nombre = ds_clean_string((string) ($body['nombre'] ?? ''), 150);
$email  = ds_validate_email((string) ($body['email'] ?? ''));
$rol    = (string) ($body['rol'] ?? '');
$activo = !empty($body['activo']);

if ($id <= 0 || $nombre === '' || $email === null) {
    ds_json_error('id, nombre y correo son requeridos', 400);
}
$email = strtolower($email);

if (!in_array($rol, [DS_ROL_LECTURA, DS_ROL_OPERADOR, DS_ROL_DUENO], true)) {
    ds_json_error('Rol inválido', 400);
}

$pdo = ds_get_pdo();

// Transacción con FOR UPDATE: un simple "contar y luego actualizar" sin lock es la misma
// condición de carrera que ya se cerró ayer en RateLimit.php — dos dueños degradándose
// mutuamente EN PARALELO podrían cada uno leer "todavía queda el otro" antes de que
// cualquiera de los dos confirme, y las dos pasar, dejando el sistema en 0 dueños. El
// FOR UPDATE bloquea la fila objetivo y las filas de dueños activos hasta el commit, así
// que la segunda transacción concurrente ve el estado ya actualizado, no uno obsoleto.
$pdo->beginTransaction();
try {
    $actual = $pdo->prepare('SELECT rol, activo FROM admins WHERE id = ? FOR UPDATE');
    $actual->execute([$id]);
    $row = $actual->fetch();
    if (!$row) { $pdo->rollBack(); ds_json_error('Cuenta no encontrada', 404); }

    // Nadie cambia su propio rol: sin esto, un Operador que llegara a este endpoint por
    // cualquier vía se auto-ascendería y el sistema de permisos sería decorativo.
    if ($id === $actorId && $rol !== $row['rol']) {
        $pdo->rollBack();
        ds_json_error('No puedes cambiar tu propio rol', 403);
    }
    // Nadie se desactiva a sí mismo (evita quedarte fuera del panel por accidente).
    if ($id === $actorId && !$activo) {
        $pdo->rollBack();
        ds_json_error('No puedes desactivar tu propia cuenta', 403);
    }

    // Invariante: siempre debe quedar al menos un dueño activo. Se dispara tanto al
    // degradar (rol deja de ser dueno) como al desactivar (activo pasa a 0) a un dueño
    // actualmente activo.
    $eraDuenoActivo = $row['rol'] === DS_ROL_DUENO && (int) $row['activo'] === 1;
    $seguiraSiendoDuenoActivo = $rol === DS_ROL_DUENO && $activo;
    if ($eraDuenoActivo && !$seguiraSiendoDuenoActivo) {
        $countStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM admins WHERE rol = 'dueno' AND activo = 1 AND id != ? FOR UPDATE"
        );
        $countStmt->execute([$id]);
        if ((int) $countStmt->fetchColumn() === 0) {
            $pdo->rollBack();
            ds_json_error('Debe quedar al menos un dueño activo — no puedes quitarle ese rol o desactivarlo', 409);
        }
    }

    $dup = $pdo->prepare('SELECT id FROM admins WHERE email = ? AND id != ?');
    $dup->execute([$email, $id]);
    if ($dup->fetch()) { $pdo->rollBack(); ds_json_error('Ya existe otra cuenta con ese correo', 409); }

    $stmt = $pdo->prepare('UPDATE admins SET nombre = ?, email = ?, rol = ?, activo = ? WHERE id = ?');
    $stmt->execute([$nombre, $email, $rol, $activo ? 1 : 0, $id]);
    $huboCambio = $stmt->rowCount() > 0;

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $e;
}

ds_admin_log('admins/update', "admin_id={$id} rol={$row['rol']}->{$rol} activo={$row['activo']}->" . ($activo ? 1 : 0));

ds_json_success(['updated' => $huboCambio]);
