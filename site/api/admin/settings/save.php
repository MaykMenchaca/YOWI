<?php
declare(strict_types=1);

require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../lib/Response.php';
require __DIR__ . '/../../lib/AdminSession.php';
require __DIR__ . '/../../lib/Validate.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') ds_json_error('Método no permitido', 405);
ds_require_admin();

$body = ds_read_json_body();
ds_admin_csrf_check($body['csrf_token'] ?? null);

// Solo se permiten estas claves (evita que se guarden claves arbitrarias).
$permitidas = [
    'nosotros_mision',
    'val1_titulo', 'val1_texto',
    'val2_titulo', 'val2_texto',
    'val3_titulo', 'val3_texto',
    'contacto_direccion', 'contacto_telefono', 'contacto_email',
    'contacto_horario', 'contacto_whatsapp',
];

$pares = isset($body['settings']) && is_array($body['settings']) ? $body['settings'] : [];

$pdo  = ds_get_pdo();
$stmt = $pdo->prepare(
    'INSERT INTO settings (clave, valor) VALUES (?, ?)
     ON DUPLICATE KEY UPDATE valor = VALUES(valor)'
);

$guardadas = 0;
foreach ($permitidas as $clave) {
    if (!array_key_exists($clave, $pares)) continue;
    $valor = ds_clean_string((string) $pares[$clave], 5000);
    // El WhatsApp: solo dígitos.
    if ($clave === 'contacto_whatsapp') {
        $valor = preg_replace('/\D+/', '', $valor);
    }
    $stmt->execute([$clave, $valor]);
    $guardadas++;
}

ds_json_success(['guardadas' => $guardadas]);
