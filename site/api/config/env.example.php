<?php
// Plantilla versionada en git — SIN secretos reales.
// Copiar a env.php (gitignored) y completar con las credenciales reales
// de la base de datos creada en hPanel (Hostinger) o en tu MySQL local
// para desarrollo.

return [
    'DB_HOST' => 'localhost',
    'DB_NAME' => 'ds_sports_supplements',
    // En producción usa el usuario de MÍNIMOS PRIVILEGIOS 'ds_app' (ver sql/provision-db-user.sql).
    'DB_USER' => 'cambiar_usuario',
    'DB_PASS' => 'cambiar_password',
    'DB_CHARSET' => 'utf8mb4',

    // ── Correo (verificación de email + recuperación de contraseña) ──────────
    // Desactivado por defecto. Para activarlo, pon MAIL_TRANSPORT en 'mail' (Hostinger)
    // o 'log' (pruebas: escribe en el log en vez de enviar). Requiere APP_URL para los enlaces.
    'MAIL_TRANSPORT' => 'none',                 // 'none' | 'log' | 'mail'
    'MAIL_FROM'      => 'no-reply@tudominio.com',
    'MAIL_FROM_NAME' => 'Distribuidor de Suplementos',
    'APP_URL'        => 'https://tudominio.com', // sin barra final; base para los enlaces del correo

    // ── IP real del cliente tras proxy/CDN (opt-in) ───────────────────────────
    // Vacío = usar solo REMOTE_ADDR (seguro por defecto). Si el sitio está detrás de
    // un proxy de confianza (p. ej. Cloudflare), pon aquí la cabecera que inyecta la
    // IP real del cliente ('CF-Connecting-IP', o 'X-Forwarded-For'). Solo actívala si
    // el proxy SIEMPRE sobreescribe esa cabecera; si no, es falsificable.
    'TRUSTED_IP_HEADER' => '', // p.ej. 'CF-Connecting-IP' si el sitio está tras Cloudflare

    // ── Cifrado en reposo del secreto TOTP (2FA de admin) ─────────────────────
    // 32 bytes aleatorios en base64. Generar UNA sola vez con:
    //   php -r "echo base64_encode(random_bytes(32));"
    // NUNCA la pierdas ni la cambies una vez que algún admin tenga el 2FA activo: hacerlo
    // invalida en silencio TODOS los secretos TOTP ya guardados y bloquea a esos admins del
    // panel (ver docs/despliegue-hostinger.md, sección de 2FA).
    'TOTP_ENCRYPTION_KEY' => 'cambiar_por_una_clave_generada_de_32_bytes_en_base64',

    // ── Google OAuth ("Iniciar sesión con Google") ────────────────────────────
    // Flujo Authorization Code del lado del servidor — SIN el SDK de Google en el
    // navegador (evitaría abrir script-src 'self' del CSP a un dominio externo).
    // Obtener en https://console.cloud.google.com/apis/credentials:
    //   1. Crear un "OAuth client ID" de tipo "Web application".
    //   2. Authorized JavaScript origin: https://distribuidordesuplementos.com.mx
    //   3. Authorized redirect URI:      https://distribuidordesuplementos.com.mx/api/auth/google-callback.php
    // Si se dejan vacíos, o si falta APP_URL de arriba, "Continuar con Google" se
    // desactiva solo (redirige con google_error=1) en vez de tronar.
    'GOOGLE_CLIENT_ID'     => '',
    'GOOGLE_CLIENT_SECRET' => '',

    // ── Debug (solo local) ────────────────────────────────────────────────────
    // 'DS_DEBUG' => '1',   // muestra errores en pantalla SOLO en desarrollo
];
