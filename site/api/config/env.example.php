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

    // ── Debug (solo local) ────────────────────────────────────────────────────
    // 'DS_DEBUG' => '1',   // muestra errores en pantalla SOLO en desarrollo
];
