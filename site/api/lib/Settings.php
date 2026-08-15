<?php
// Fuente ÚNICA de verdad de las claves de la tabla `settings`.
//
// Antes esta lista vivía copiada en cuatro lugares (save.php, get.php público, backup/import.php
// y el JS del panel) y había que sincronizarlos a mano; el propio get.php lo admitía en un
// comentario. Agregar una clave nueva ahora se hace SOLO aquí.
//
// Cada clave declara su tipo (cómo se valida) y su longitud máxima. El tope fijo de 5000
// caracteres que se usaba antes servía para textos cortos pero no para los legales, que pueden
// pasar de 15 mil.

declare(strict_types=1);

require_once __DIR__ . '/Validate.php';

/**
 * clave => ['tipo' => texto|email|url|digitos|textolargo, 'max' => bytes]
 */
function ds_settings_definicion(): array
{
    static $def = null;
    if ($def !== null) return $def;

    $def = [
        // ── Página "Nosotros" (contenido editable) ────────────────────────────
        'nosotros_mision'       => ['tipo' => 'texto',      'max' => 5000],
        'nosotros_vision'       => ['tipo' => 'texto',      'max' => 5000],
        'nosotros_quienes'      => ['tipo' => 'textolargo', 'max' => 20000],
        'nosotros_que_hacemos'  => ['tipo' => 'texto',      'max' => 5000],
        // Una línea = un beneficio. La rejilla del sitio se adapta al número de líneas.
        'nosotros_beneficios'   => ['tipo' => 'texto',      'max' => 5000],
        'nosotros_representa'   => ['tipo' => 'textolargo', 'max' => 20000],

        // ── Contacto ──────────────────────────────────────────────────────────
        'contacto_direccion'    => ['tipo' => 'texto',   'max' => 5000],
        'contacto_telefono'     => ['tipo' => 'texto',   'max' => 60],
        'contacto_email'        => ['tipo' => 'email',   'max' => 190],
        'contacto_horario'      => ['tipo' => 'texto',   'max' => 5000],
        'contacto_whatsapp'     => ['tipo' => 'digitos', 'max' => 20],
        'contacto_mapa_url'     => ['tipo' => 'url',     'max' => 500],

        // ── Redes sociales (opcionales: vacías = no se muestran) ──────────────
        'social_facebook'       => ['tipo' => 'url', 'max' => 500],
        'social_instagram'      => ['tipo' => 'url', 'max' => 500],
        'social_tiktok'         => ['tipo' => 'url', 'max' => 500],

        // ── Identidad del negocio (para el aviso de privacidad) ───────────────
        'negocio_razon_social'  => ['tipo' => 'texto', 'max' => 200],
        'negocio_rfc'           => ['tipo' => 'texto', 'max' => 20],

        // ── Textos legales ────────────────────────────────────────────────────
        'legal_compra'          => ['tipo' => 'textolargo', 'max' => 30000],
        'legal_envio'           => ['tipo' => 'textolargo', 'max' => 30000],
        'legal_terminos'        => ['tipo' => 'textolargo', 'max' => 30000],
        'legal_privacidad'      => ['tipo' => 'textolargo', 'max' => 30000],
    ];
    return $def;
}

/** Lista plana de claves válidas. */
function ds_settings_claves(): array
{
    return array_keys(ds_settings_definicion());
}

function ds_settings_es_valida(string $clave): bool
{
    return array_key_exists($clave, ds_settings_definicion());
}

/**
 * Valida y limpia un valor según el tipo de su clave.
 * Devuelve ['ok' => true, 'valor' => string] o ['ok' => false, 'error' => string].
 *
 * Vaciar un campo siempre es válido: borrar un dato opcional (una red social, el RFC) es una
 * acción legítima del dueño, no un error.
 */
function ds_settings_limpiar(string $clave, string $valor): array
{
    $def = ds_settings_definicion();
    if (!isset($def[$clave])) {
        return ['ok' => false, 'error' => "Campo desconocido: $clave"];
    }
    $tipo = $def[$clave]['tipo'];
    $max  = $def[$clave]['max'];

    $valor = trim($valor);
    if ($valor === '') {
        return ['ok' => true, 'valor' => ''];
    }

    switch ($tipo) {
        case 'digitos':
            $limpio = preg_replace('/\D+/', '', $valor) ?? '';
            if ($limpio === '') {
                return ['ok' => false, 'error' => 'El número de WhatsApp debe contener dígitos (ej. 5218331645172).'];
            }
            return ['ok' => true, 'valor' => mb_substr($limpio, 0, $max)];

        case 'email':
            $limpio = ds_validate_email($valor);
            if ($limpio === null) {
                return ['ok' => false, 'error' => 'El correo de contacto no es válido.'];
            }
            return ['ok' => true, 'valor' => mb_substr($limpio, 0, $max)];

        case 'url':
            // ds_clean_url solo acepta http/https o rutas relativas; bloquea javascript:, data:, etc.
            $limpio = ds_clean_url($valor, $max);
            if ($limpio === null) {
                return ['ok' => false, 'error' => 'El enlace no es válido. Debe empezar con https://'];
            }
            return ['ok' => true, 'valor' => $limpio];

        case 'texto':
        case 'textolargo':
        default:
            return ['ok' => true, 'valor' => ds_clean_string($valor, $max)];
    }
}
