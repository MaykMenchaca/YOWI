<?php
declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../lib/Response.php';
require __DIR__ . '/../lib/Session.php';
require __DIR__ . '/../lib/Csrf.php';
require __DIR__ . '/../lib/Validate.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ds_json_error('Método no permitido', 405);
}

$userId = ds_require_login();

$body = ds_read_json_body();
ds_csrf_check($body['csrf_token'] ?? null);

$id          = ds_to_positive_int($body['id'] ?? 0);
$etiqueta    = ds_clean_string((string) ($body['etiqueta'] ?? ''), 60);
$nombre      = ds_clean_string((string) ($body['nombre'] ?? ''), 150);
$telefono    = ds_clean_string((string) ($body['telefono'] ?? ''), 30);
$calle       = ds_clean_string((string) ($body['calle'] ?? ''), 255);
$colonia     = ds_clean_string((string) ($body['colonia'] ?? ''), 150);
$cp          = ds_clean_string((string) ($body['cp'] ?? ''), 10);
$ciudad      = ds_clean_string((string) ($body['ciudad'] ?? ''), 120);
$estado      = ds_clean_string((string) ($body['estado'] ?? ''), 120);
$referencias = ds_clean_string((string) ($body['referencias'] ?? ''), 255);
$predet      = !empty($body['es_predeterminada']);

if ($nombre === '') {
    ds_json_error('El nombre es obligatorio', 400);
}
if ($calle === '') {
    ds_json_error('La calle es obligatoria', 400);
}
if ($cp !== '' && !preg_match('/^\d{5}$/', $cp)) {
    ds_json_error('El código postal son 5 dígitos', 400);
}

$pdo = ds_get_pdo();

// Nulos limpios para columnas opcionales.
$n = static fn(string $v): ?string => $v !== '' ? $v : null;

$pdo->beginTransaction();
try {
    if ($id > 0) {
        // Confirmar que la dirección pertenece al usuario en sesión.
        $own = $pdo->prepare('SELECT id FROM user_addresses WHERE id = ? AND user_id = ?');
        $own->execute([$id, $userId]);
        if (!$own->fetch()) {
            $pdo->rollBack();
            ds_json_error('Dirección no encontrada', 404);
        }
        $upd = $pdo->prepare(
            'UPDATE user_addresses
             SET etiqueta = ?, nombre = ?, telefono = ?, calle = ?, colonia = ?, cp = ?,
                 ciudad = ?, estado = ?, referencias = ?, es_predeterminada = ?
             WHERE id = ? AND user_id = ?'
        );
        $upd->execute([
            $n($etiqueta), $nombre, $n($telefono), $calle, $n($colonia), $n($cp),
            $n($ciudad), $n($estado), $n($referencias), $predet ? 1 : 0, $id, $userId,
        ]);
        $savedId = $id;
    } else {
        $ins = $pdo->prepare(
            'INSERT INTO user_addresses
             (user_id, etiqueta, nombre, telefono, calle, colonia, cp, ciudad, estado, referencias, es_predeterminada)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $ins->execute([
            $userId, $n($etiqueta), $nombre, $n($telefono), $calle, $n($colonia), $n($cp),
            $n($ciudad), $n($estado), $n($referencias), $predet ? 1 : 0,
        ]);
        $savedId = (int) $pdo->lastInsertId();
    }

    // Predeterminada exclusiva: si esta se marcó, desmarcar las demás del usuario.
    if ($predet) {
        $clr = $pdo->prepare('UPDATE user_addresses SET es_predeterminada = 0 WHERE user_id = ? AND id <> ?');
        $clr->execute([$userId, $savedId]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('addresses/save.php: ' . $e->getMessage());
    ds_json_error('No se pudo guardar la dirección', 500);
}

ds_json_success(['id' => $savedId]);
