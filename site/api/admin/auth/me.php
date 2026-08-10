<?php
declare(strict_types=1);

require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../lib/Response.php';
require __DIR__ . '/../../lib/AdminSession.php';

// GET /api/admin/auth/me.php — devuelve el admin actual + csrf_token.
// Si no hay sesión activa devuelve admin:null (no es error).
//
// Usa ds_admin_actual() (caché estática, misma consulta que ds_require_admin()) en vez de
// su propio SELECT: así hereda gratis el chequeo de cuenta desactivada y de contraseña
// cambiada — si cualquiera de los dos cerró la sesión, ds_admin_actual() ya devuelve null.
$admin = ds_admin_actual();

if ($admin === null) {
    ds_json_success(['admin' => null, 'csrf_token' => ds_admin_csrf_token()]);
}

ds_json_success([
    'admin'      => [
        'id'     => $admin['id'],
        'nombre' => $admin['nombre'],
        'email'  => $admin['email'],
        'rol'    => $admin['rol'],
    ],
    // 2FA obligatorio: si aún no lo activó, el front lo lleva a enrolarse antes de operar.
    'needs_2fa'  => !$admin['totp_enabled'],
    'csrf_token' => ds_admin_csrf_token(),
]);
