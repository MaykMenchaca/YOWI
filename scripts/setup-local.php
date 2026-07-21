<?php
// ─────────────────────────────────────────────────────────────────────────────
// Setup local en UN comando:  php scripts/setup-local.php
//
// Hace todo lo necesario para tener el sitio corriendo en local con MySQL:
//   1. Crea site/api/config/env.php (si no existe) con valores por defecto de
//      MySQL local (host 127.0.0.1, user root, sin contraseña).
//   2. Crea la base de datos si no existe.
//   3. Importa sql/schema.sql (si faltan las tablas).
//   4. Siembra los productos demo (solo si el catálogo está vacío).
//   5. Crea el admin por defecto (solo si no hay admins).
//
// Es IDEMPOTENTE: puedes correrlo las veces que quieras; no duplica nada.
// Uso opcional: php scripts/setup-local.php "correo@admin.com" "TuPassword"
// ─────────────────────────────────────────────────────────────────────────────

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    echo "Solo ejecutable desde la línea de comandos.\n";
    exit(1);
}

$ROOT      = dirname(__DIR__);
$ENV_PATH  = $ROOT . '/site/api/config/env.php';
$SCHEMA    = $ROOT . '/sql/schema.sql';

// Credenciales del admin (puedes pasarlas como argumentos).
$ADMIN_NOMBRE = 'Admin DS';
$ADMIN_EMAIL  = $argv[1] ?? 'admin@ds.com';
$ADMIN_PASS   = $argv[2] ?? 'AdminDS2026';

function paso(string $msg): void { echo "\n\033[1m▶ {$msg}\033[0m\n"; }
function ok(string $msg): void   { echo "  \033[32m✓\033[0m {$msg}\n"; }
function info(string $msg): void { echo "    {$msg}\n"; }

echo "═══════════════════════════════════════════════════\n";
echo "  Setup local — Distribuidor de Suplementos\n";
echo "═══════════════════════════════════════════════════\n";

// ── 1. env.php ───────────────────────────────────────────────────────────────
paso('Configuración de conexión (env.php)');
if (!file_exists($ENV_PATH)) {
    $plantilla = <<<PHP
<?php
// Generado por setup-local.php — valores por defecto para MySQL local.
// Ajusta si tu MySQL usa otro usuario/contraseña/puerto.
return [
    'DB_HOST'    => '127.0.0.1',
    'DB_NAME'    => 'ds_sports_supplements',
    'DB_USER'    => 'root',
    'DB_PASS'    => '',
    'DB_CHARSET' => 'utf8mb4',
];

PHP;
    file_put_contents($ENV_PATH, $plantilla);
    ok("Creado site/api/config/env.php (root, sin contraseña).");
} else {
    ok("Ya existe env.php — se respeta tu configuración.");
}

$env = require $ENV_PATH;
$host    = $env['DB_HOST'] ?? '127.0.0.1';
$port    = $env['DB_PORT'] ?? null;
$dbName  = $env['DB_NAME'] ?? 'ds_sports_supplements';
$user    = $env['DB_USER'] ?? 'root';
$pass    = $env['DB_PASS'] ?? '';
$charset = $env['DB_CHARSET'] ?? 'utf8mb4';
$portDsn = $port ? ';port=' . $port : '';

// ── 2. Crear la base de datos ────────────────────────────────────────────────
paso('Base de datos');
try {
    $root = new PDO("mysql:host={$host}{$portDsn};charset={$charset}", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (PDOException $e) {
    echo "\n\033[31m✗ No se pudo conectar a MySQL.\033[0m\n";
    info("¿Está MySQL encendido? (inicia el servicio MySQL en Windows).");
    info("Detalle: " . $e->getMessage());
    exit(1);
}
$root->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
ok("Base de datos '{$dbName}' lista.");

$pdo = new PDO("mysql:host={$host}{$portDsn};dbname={$dbName};charset={$charset}", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// ── 3. Importar el schema ────────────────────────────────────────────────────
paso('Estructura (tablas)');
$tablasExisten = $pdo->query("SHOW TABLES LIKE 'products'")->fetch();
if ($tablasExisten) {
    ok("Las tablas ya existen — se omite la importación.");
} else {
    $sql = file_get_contents($SCHEMA);
    // Quitar comentarios de línea (--) y partir por ';'
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    $statements = array_filter(array_map('trim', explode(';', $sql)), fn($s) => $s !== '');
    foreach ($statements as $stmt) {
        $pdo->exec($stmt);
    }
    ok("Importadas " . count($statements) . " sentencias de sql/schema.sql.");
}

// ── 4. Sembrar productos (solo si el catálogo está vacío) ─────────────────────
paso('Productos');
$nProd = (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
if ($nProd > 0) {
    ok("Ya hay {$nProd} productos — se omite el seed.");
} else {
    passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/seed-products.php'), $code);
    if ($code !== 0) { echo "\n\033[31m✗ Falló el seed de productos.\033[0m\n"; exit(1); }
    $nProd = (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
    ok("Sembrados {$nProd} productos.");
}

// ── 5. Crear admin (solo si no hay ninguno) ──────────────────────────────────
paso('Usuario administrador');
$nAdmin = (int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
if ($nAdmin > 0) {
    ok("Ya existe al menos un admin — se omite la creación.");
    info("Si olvidaste la contraseña, crea otro con:");
    info('  php scripts/create-admin.php "Nombre" "otro@correo.com" "OtraPass"');
} else {
    passthru(
        escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/create-admin.php') . ' ' .
        escapeshellarg($ADMIN_NOMBRE) . ' ' . escapeshellarg($ADMIN_EMAIL) . ' ' . escapeshellarg($ADMIN_PASS),
        $code
    );
    if ($code !== 0) { echo "\n\033[31m✗ No se pudo crear el admin.\033[0m\n"; exit(1); }
    ok("Admin creado.");
}

// ── Resumen ──────────────────────────────────────────────────────────────────
echo "\n═══════════════════════════════════════════════════\n";
echo "  \033[32m¡Todo listo!\033[0m\n";
echo "═══════════════════════════════════════════════════\n";
echo "\n  Levanta el sitio:\n";
echo "     php -S localhost:8080 -t site      (o doble clic a start-local.bat)\n";
echo "\n  Tienda:  http://localhost:8080\n";
echo "  Admin:   http://localhost:8080/admin/login.html\n";
if ($nAdmin === 0) {
    echo "           Correo:     {$ADMIN_EMAIL}\n";
    echo "           Contraseña: {$ADMIN_PASS}\n";
}
echo "\n";
