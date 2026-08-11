<?php
// Sanitización/validación mínima de inputs de la API.

declare(strict_types=1);

function ds_validate_email(string $email): ?string
{
    $email = trim($email);
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
}

function ds_clean_string(string $value, int $maxLength = 255): string
{
    return mb_substr(trim($value), 0, $maxLength);
}

/**
 * Política única de contraseñas del proyecto (clientes y admins).
 * Devuelve el mensaje de error a mostrar, o null si la contraseña es aceptable.
 *
 * El mínimo se mide con mb_strlen (caracteres) y el máximo con strlen (bytes) a propósito:
 *  - mb_strlen para el mínimo porque strlen cuenta bytes, y así "áéíóú" (5 caracteres,
 *    10 bytes en UTF-8) pasaba el filtro de 8 sin llegar realmente a 8 caracteres.
 *  - strlen para el máximo porque 72 bytes es el límite real de bcrypt: a partir de ahí
 *    trunca en silencio, así que una contraseña de 100 caracteres equivale a sus primeros
 *    72 y el usuario nunca se entera. Se rechaza explícitamente en vez de truncar.
 */
function ds_validate_password(string $pass): ?string
{
    if (mb_strlen($pass) < 8) {
        return 'La contraseña debe tener al menos 8 caracteres';
    }
    if (strlen($pass) > 72) {
        return 'La contraseña es demasiado larga: el máximo son 72 bytes '
             . '(los acentos y emojis ocupan más de un byte). Usa una más corta.';
    }

    // Lista de las contraseñas más usadas en la práctica, con variantes en español y del
    // propio negocio. static: se construye una sola vez por proceso, no en cada llamada.
    static $comunes = [
        // Numéricas y secuencias de teclado
        '123456', '1234567', '12345678', '123456789', '1234567890', '12345', '123123',
        '1234', '111111', '000000', '123321', '654321', '666666', '121212', '112233',
        '987654321', '987654', '11111111', '22222222', '88888888', '99999999', '12341234',
        '123454321', '147258369', '159753', '741852963', '5201314', '123456a', '1234abcd',
        'abc123', 'abcd1234', 'abcd123', 'a1b2c3d4', 'qwerty', 'qwerty1', 'qwerty123',
        'qwerty1234', 'qwertyuiop', 'qwertyui', 'qwe123', 'qwe123456', '123qwe', 'asd123',
        'asdf1234', 'asdfgh', 'asdfghjkl', 'zxcvbnm', 'qazwsx', 'zaq12wsx', '1qaz2wsx',
        '1q2w3e4r', '1q2w3e',
        // Clásicas en inglés
        'password', 'password1', 'password123', 'passw0rd', 'pass1234', 'letmein',
        'welcome', 'welcome1', 'welcome123', 'admin', 'admin123', 'admin1234',
        'administrator', 'root', 'toor', 'iloveyou', 'princess', 'sunshine', 'monkey',
        'dragon', 'football', 'baseball', 'superman', 'batman', 'jordan', 'harley',
        'ranger', 'shadow', 'master', 'hunter', 'trustno1', 'freedom', 'whatever',
        'computer', 'internet', 'samsung', 'google', 'facebook', 'starwars', 'pokemon',
        'minecraft', 'chocolate', 'cookie', 'flower', 'secret', 'ninja', 'soccer',
        'hockey', 'killer', 'matrix', 'changeme', 'default', 'login', 'guest123',
        'test123', 'test1234', 'testing123', 'temp1234', 'user1234', 'michael',
        'jennifer', 'michelle', 'ashley', 'charlie', 'thomas', 'robert', 'daniel',
        'andrew', 'joshua', 'william', 'nicole', 'jessica', 'amanda', 'lovely', 'angel',
        'summer', 'winter', 'hello', 'hello123', 'buster', 'tigger', 'purple', 'orange',
        'silver', 'banana', 'cheese', 'pepper', 'coffee', 'guitar', 'forever', 'friends',
        // Español
        'contraseña', 'contrasena', 'contraseña123', 'contrasena123', 'micontraseña',
        'micontrasena', 'clave', 'clave123', 'claveadmin', 'secreto', 'secreto123',
        'usuario', 'usuario123', 'administrador', 'administrador123', 'hola', 'hola123',
        'holamundo', 'holaquetal', 'tequiero', 'teamo', 'teamomucho', 'mivida', 'mihija',
        'mihijo', 'familia', 'familia123', 'amor', 'amor123', 'amores', 'corazon',
        'corazon123', 'princesa', 'princesa123', 'hermosa', 'bonita', 'chiquita',
        'mamita', 'papito', 'jesus', 'jesucristo', 'dios', 'bendicion', 'esperanza',
        'libertad', 'estrella', 'mariposa', 'girasol', 'cerveza', 'tequila', 'michelada',
        'perrito', 'gatito', 'mexico', 'mexico123', 'mexicano', 'vivamexico',
        'guadalajara', 'monterrey', 'cancun', 'america', 'chivas', 'cruzazul', 'pumas',
        'futbol', 'futbol123', 'barcelona', 'realmadrid', 'colombia', 'argentina',
        'alejandro', 'alejandra', 'fernando', 'fernanda', 'francisco', 'guadalupe',
        'mariana', 'carolina', 'patricia', 'ricardo', 'roberto', 'gabriela', 'veronica',
        'cristina', 'antonio', 'manuel', 'carlos', 'carlos123', 'javier', 'sebastian',
        'valentina', 'santiago', 'españa', 'espana', 'prueba', 'prueba123', 'ejemplo',
        'ejemplo123', 'temporal', 'temporal123', 'nueva123', 'ninguna',
        // Variantes obvias de este negocio
        'suplementos', 'suplementos123', 'distribuidor', 'distribuidor123',
        'distribuidordesuplementos', 'yowi', 'yowi123', 'yowi2026', 'dsadmin', 'adminds',
        'proteina', 'proteina123', 'whey', 'wheyprotein', 'creatina', 'gimnasio', 'gym123',
        'fitness', 'fitness123', 'musculo', 'tienda', 'tienda123', 'tiendaonline',
        'ventas', 'ventas123', 'almacen', 'inventario', 'catalogo', 'pedidos123',
    ];

    if (in_array(mb_strtolower(trim($pass)), $comunes, true)) {
        return 'Esa contraseña es demasiado común y fácil de adivinar. Elige otra distinta.';
    }

    return null;
}

function ds_to_positive_float($value): float
{
    $f = (float) $value;
    return $f > 0 ? $f : 0.0;
}

function ds_to_positive_int($value): int
{
    $i = (int) $value;
    return $i > 0 ? $i : 0;
}

/**
 * Limpia una URL para usarla como enlace clicable (href).
 * Permite solo esquemas http/https o rutas relativas (sin esquema).
 * Bloquea esquemas peligrosos como javascript: o data: que provocarían XSS.
 * Devuelve null si queda vacía o si el esquema no está permitido.
 */
function ds_clean_url(string $value, int $maxLength = 500): ?string
{
    // Quitar caracteres de control (incluye tab/CR/LF) ANTES de mirar el esquema. Sin
    // esto, "java\tscript:alert(1)" no calza con el regex de abajo (el tab rompe la
    // secuencia "javascript") y la cadena se acepta tal cual — el navegador ignora los
    // caracteres de control al interpretar un esquema, así que sí lo ejecutaría. Mismo
    // endurecimiento que window.DSSec.safeHref en site/assets/js/security-utils.js.
    $value = preg_replace('/[\x00-\x1F\x7F]+/', '', $value) ?? '';
    $value = ds_clean_string($value, $maxLength);
    if ($value === '') {
        return null;
    }
    // Protocolo-relativo ("//host"): hereda el esquema de la página, en la práctica
    // equivale a aceptar cualquier esquema. Rechazarlo también.
    if (str_starts_with($value, '//')) {
        return null;
    }
    // Si la cadena empieza con "esquema:", solo aceptar http/https.
    if (preg_match('/^\s*([a-z][a-z0-9+.\-]*)\s*:/i', $value, $m)) {
        $scheme = strtolower($m[1]);
        if ($scheme !== 'http' && $scheme !== 'https') {
            return null;
        }
    }
    return $value;
}
