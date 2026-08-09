-- Guarda el id del sabor elegido en cada línea de pedido, no solo su nombre en texto.
--
-- Hasta ahora order_items.sabor solo guardaba el nombre ("Chocolate") como texto suelto:
-- si el admin renombraba el sabor no había forma de saber de qué fila de product_flavors
-- salió, y por lo tanto tampoco de reponer el stock correcto al cancelar un pedido. Esta
-- columna resuelve eso sin tocar `sabor` (se conserva como registro histórico legible,
-- por si el sabor se borra por completo más adelante).
--
-- NULL a propósito: productos sin sabores, y pedidos anteriores a esta migración que no
-- se puedan emparejar por nombre en el backfill, se quedan en NULL (no controlan stock
-- por sabor al cancelar — se maneja como antes, a nivel producto).
--
-- Idempotente y portable (MariaDB + MySQL 8): information_schema + PREPARE/EXECUTE, mismo
-- patrón que el resto de migraciones de este proyecto.

SET @existe_col := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order_items' AND COLUMN_NAME = 'sabor_id'
);
SET @sql_col := IF(@existe_col = 0,
    'ALTER TABLE order_items ADD COLUMN sabor_id INT UNSIGNED NULL AFTER producto_id',
    'DO 0'
);
PREPARE st_col FROM @sql_col;
EXECUTE st_col;
DEALLOCATE PREPARE st_col;

-- Backfill best-effort: empareja por (producto_id, nombre de sabor) los pedidos ya
-- existentes. Si el sabor fue renombrado o borrado desde entonces, no hay match y la fila
-- se queda en NULL — no es un error, es lo mejor que se puede reconstruir con lo que hay.
UPDATE order_items oi
JOIN product_flavors pf
  ON pf.product_id = oi.producto_id AND pf.nombre = oi.sabor
SET oi.sabor_id = pf.id
WHERE oi.sabor_id IS NULL AND oi.sabor IS NOT NULL AND oi.producto_id IS NOT NULL;

-- Índice para las búsquedas de "reponer stock de este sabor" al cancelar un pedido.
SET @existe_idx := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order_items' AND INDEX_NAME = 'idx_items_sabor'
);
SET @sql_idx := IF(@existe_idx = 0,
    'ALTER TABLE order_items ADD KEY idx_items_sabor (sabor_id)',
    'DO 0'
);
PREPARE st_idx FROM @sql_idx;
EXECUTE st_idx;
DEALLOCATE PREPARE st_idx;

-- FK con ON DELETE SET NULL: si el admin borra el sabor por completo (no solo lo
-- desactiva), la línea del pedido conserva su historial (sabor en texto) pero pierde el
-- enlace — igual que ya se hace con fk_items_product.
SET @existe_fk := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'order_items'
      AND CONSTRAINT_NAME = 'fk_items_flavor' AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql_fk := IF(@existe_fk = 0,
    'ALTER TABLE order_items ADD CONSTRAINT fk_items_flavor FOREIGN KEY (sabor_id) REFERENCES product_flavors(id) ON DELETE SET NULL',
    'DO 0'
);
PREPARE st_fk FROM @sql_fk;
EXECUTE st_fk;
DEALLOCATE PREPARE st_fk;
