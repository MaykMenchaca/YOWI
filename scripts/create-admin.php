<?php
// Uso: php scripts/create-admin.php "Nombre Apellido" "email@dominio.com" "password"
// Solo se ejecuta desde la línea de comandos. No hay endpoint HTTP para crear admins.

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    echo "Solo ejecutable desde CLI.\n";
    exit(1);
}

if ($argc < 4) {
    echo "Uso: php create-admin.php \"Nombre\" \"email@dominio.com\" \"password\"\n";
    exit(1);
}

require __DIR__ . '/../site/api/lib/Validate.php';

$nombre = trim($argv[1]);
$email  = trim($argv[2]);
$pass   = $argv[3];

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "Email inválido: $email\n";
    exit(1);
}
if (($errPass = ds_validate_password($pass)) !== null) {
    echo "$errPass\n";
    exit(1);
}

require __DIR__ . '/../site/api/config/database.php';

$pdo = ds_get_pdo();

$check = $pdo->prepare('SELECT id FROM admins WHERE email = ?');
$check->execute([$email]);
if ($check->fetch()) {
    echo "Ya existe un admin con el email: $email\n";
    exit(1);
}

$hash = password_hash($pass, PASSWORD_DEFAULT);
// Siempre 'dueno': este script es el único camino para crear el primer admin de una
// instalación nueva. El default de la columna es 'lectura' (el rol menos privilegiado,
// para que un INSERT futuro que olvide el rol no cree accidentalmente un dueño) — pero
// eso dejaría al propio dueño fuera de su panel al arrancar. Para crear empleados con
// rol acotado, se usa la pantalla "Usuarios" del panel (site/api/admin/admins/create.php),
// no este script.
$stmt = $pdo->prepare('INSERT INTO admins (nombre, email, password_hash, rol) VALUES (?, ?, ?, ?)');
$stmt->execute([$nombre, $email, $hash, 'dueno']);

echo "Admin creado exitosamente. ID = " . $pdo->lastInsertId() . "\n";
echo "  Nombre: $nombre\n";
echo "  Email:  $email\n";
echo "  Rol:    dueno\n";
