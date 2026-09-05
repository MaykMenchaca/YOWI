-- Habilita "Iniciar sesión con Google" (OAuth 2.0, código de autorización, server-side).
-- password_hash pasa a NULL-able (cuentas creadas solo vía Google no tienen contraseña
-- propia) y se agrega users.google_id (sub de Google, único) para vincular/crear cuentas.
--
-- Idempotente y portable: ver sql/migrations/2026-08-02-password-changed-at.sql para el
-- porqué de este patrón (information_schema en vez de "ADD COLUMN IF NOT EXISTS", que
-- MySQL 8 rechaza).

-- 1) password_hash NULL-able: MODIFY COLUMN es idempotente por sí mismo, sin chequeo.
ALTER TABLE users MODIFY COLUMN password_hash VARCHAR(255) NULL;

-- 2) Nueva columna google_id (sub de Google; string numérico, sin longitud máxima
--    garantizada por Google — 64 deja margen amplio).
SET @existe_col := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'google_id'
);
SET @sql_col := IF(@existe_col = 0,
    'ALTER TABLE users ADD COLUMN google_id VARCHAR(64) NULL AFTER password_hash',
    'DO 0'
);
PREPARE st_col FROM @sql_col;
EXECUTE st_col;
DEALLOCATE PREPARE st_col;

-- 3) Índice único sobre google_id. MySQL/MariaDB permiten múltiples NULL en un índice
--    único (NULL nunca es igual a NULL para esa restricción), así que las cuentas
--    password-only (sin Google) no chocan entre sí.
SET @existe_idx := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'uq_users_google_id'
);
SET @sql_idx := IF(@existe_idx = 0,
    'ALTER TABLE users ADD UNIQUE KEY uq_users_google_id (google_id)',
    'DO 0'
);
PREPARE st_idx FROM @sql_idx;
EXECUTE st_idx;
DEALLOCATE PREPARE st_idx;
