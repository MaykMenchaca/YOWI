<?php
declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../lib/Response.php';

// GET /api/settings/get.php — contenido editable (público), como objeto {clave: valor}.
//
// Allowlist explícita de lo que este endpoint puede publicar. admin/settings/save.php ya
// restringe a estas mismas claves lo que se puede ESCRIBIR (evita mantenerlas sincronizadas
// a mano si cambian), pero este SELECT solo no lo garantizaba: cualquier fila que algún día
// se insertara directo en la tabla (una migración, un script interno) se habría publicado
// sola sin que nadie lo pidiera. Si agregas una clave nueva en save.php, agrégala aquí
// también para que se muestre en el sitio.
$permitidas = [
    'nosotros_mision',
    'val1_titulo', 'val1_texto',
    'val2_titulo', 'val2_texto',
    'val3_titulo', 'val3_texto',
    'contacto_direccion', 'contacto_telefono', 'contacto_email',
    'contacto_horario', 'contacto_whatsapp',
];

$pdo  = ds_get_pdo();
$rows = $pdo->query('SELECT clave, valor FROM settings')->fetchAll();

$data = [];
foreach ($rows as $r) {
    if (in_array($r['clave'], $permitidas, true)) {
        $data[$r['clave']] = $r['valor'];
    }
}

ds_json_success($data);
