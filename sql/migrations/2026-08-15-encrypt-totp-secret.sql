-- Cifra en reposo admins.totp_secret (antes texto plano base32). El cifrado (nonce +
-- ciphertext + MAC en base64, ver site/api/lib/Crypto.php) ocupa más que el base32
-- original, así que primero se ensancha la columna.
--
-- Los secretos YA guardados (texto plano) no se re-cifran: sitio pre-lanzamiento, sin
-- admins reales enrolados todavía — solo datos de prueba. En vez de una migración de datos
-- que intente distinguir "plano" de "cifrado" (frágil: ambos son cadenas ASCII de longitud
-- parecida), se invalidan una sola vez y se fuerza reenrolamiento por el flujo normal
-- (2fa-setup.php → 2fa-activate.php). El bloque de invalidación está protegido por la MISMA
-- condición que el ALTER, así que solo corre la primera vez que se aplica esta migración —
-- reejecutarla después no vuelve a tocar secretos ya cifrados.
--
-- Idempotente y portable (MariaDB + MySQL 8): information_schema + PREPARE/EXECUTE, mismo
-- patrón que el resto de migraciones de este proyecto.

SET @len := (
    SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admins' AND COLUMN_NAME = 'totp_secret'
);

SET @sql_widen := IF(@len IS NULL OR @len < 255,
    'ALTER TABLE admins MODIFY COLUMN totp_secret VARCHAR(255) NULL',
    'DO 0'
);
PREPARE st_widen FROM @sql_widen;
EXECUTE st_widen;
DEALLOCATE PREPARE st_widen;

SET @sql_invalidate := IF(@len IS NULL OR @len < 255,
    'UPDATE admins SET totp_secret = NULL, totp_enabled = 0 WHERE totp_secret IS NOT NULL',
    'DO 0'
);
PREPARE st_invalidate FROM @sql_invalidate;
EXECUTE st_invalidate;
DEALLOCATE PREPARE st_invalidate;
