-- Registrar cuándo se cambió por última vez la contraseña de un usuario, para
-- invalidar sesiones abiertas anteriores a un reset.
--
-- Idempotente y portable: "ADD COLUMN IF NOT EXISTS" es sintaxis exclusiva de MariaDB
-- (MySQL 8 la rechaza con error de sintaxis 1064). Se usa el patrón estándar, válido en
-- ambos motores: consultar information_schema y construir el ALTER solo si hace falta.
SET @existe_col := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'password_changed_at'
);
SET @sql_col := IF(@existe_col = 0,
    'ALTER TABLE users ADD COLUMN password_changed_at DATETIME NULL AFTER password_hash',
    'DO 0'
);
PREPARE st_col FROM @sql_col;
EXECUTE st_col;
DEALLOCATE PREPARE st_col;
