-- SKU: identidad única y estable de cada producto.
--
-- Permite que el CSV se reimporte sin duplicar productos aunque cambie el nombre, la
-- marca o la presentación. Antes el emparejamiento era por nombre+marca+cantidad+unidad,
-- así que corregir una falta de ortografía creaba un producto nuevo.
--
-- NULL permitido a propósito: en MySQL/MariaDB varios NULL NO colisionan en un índice
-- UNIQUE, así conviven los productos que aún no tienen SKU asignado. El importador se
-- lo graba en la primera pasada (emparejando por el método antiguo).
--
-- Idempotente y portable: "ADD COLUMN IF NOT EXISTS" es sintaxis exclusiva de MariaDB
-- (MySQL 8 la rechaza con error de sintaxis 1064). Se usa el patrón estándar, válido en
-- ambos motores: consultar information_schema y construir el ALTER solo si hace falta.

SET @existe_col := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'sku'
);
SET @sql_col := IF(@existe_col = 0,
    'ALTER TABLE products ADD COLUMN sku VARCHAR(64) NULL AFTER id',
    'DO 0'
);
PREPARE st_col FROM @sql_col;
EXECUTE st_col;
DEALLOCATE PREPARE st_col;

-- El índice único se crea aparte, con su propia guarda (información_schema.STATISTICS),
-- para que re-ejecutar esta migración a mano (CLI/phpMyAdmin) nunca falle con
-- "Duplicate key name" si ya existe.
SET @existe_idx := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND INDEX_NAME = 'uq_products_sku'
);
SET @sql_idx := IF(@existe_idx = 0,
    'ALTER TABLE products ADD UNIQUE KEY uq_products_sku (sku)',
    'DO 0'
);
PREPARE st_idx FROM @sql_idx;
EXECUTE st_idx;
DEALLOCATE PREPARE st_idx;
