-- Migración: agrega la columna direccion_envio a orders (checkout con envío a domicilio).
-- Ejecutar en bases de datos ya desplegadas; en instalaciones nuevas ya viene en schema.sql.
--
-- Idempotente y portable: "ADD COLUMN IF NOT EXISTS" es sintaxis exclusiva de MariaDB
-- (MySQL 8 la rechaza con error de sintaxis 1064). Se usa el patrón estándar, válido en
-- ambos motores: consultar information_schema y construir el ALTER solo si hace falta.
SET @existe_col := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'direccion_envio'
);
SET @sql_col := IF(@existe_col = 0,
    'ALTER TABLE orders ADD COLUMN direccion_envio TEXT NULL AFTER telefono',
    'DO 0'
);
PREPARE st_col FROM @sql_col;
EXECUTE st_col;
DEALLOCATE PREPARE st_col;
