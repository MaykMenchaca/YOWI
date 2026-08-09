-- Marca si el stock de un pedido cancelado YA fue repuesto al inventario.
--
-- Sin esto, cancelar el mismo pedido dos veces (o cancelar → reactivar → cancelar de
-- nuevo) repondría el stock más de una vez, inflando el inventario de la nada. Con el
-- marcador, admin/orders/update-status.php solo repone una vez por cada cancelación real
-- y sabe cuándo debe volver a descontar si el pedido se reactiva.
--
-- Idempotente y portable (MariaDB + MySQL 8): information_schema + PREPARE/EXECUTE.

SET @existe_col := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'stock_repuesto'
);
SET @sql_col := IF(@existe_col = 0,
    'ALTER TABLE orders ADD COLUMN stock_repuesto TINYINT(1) NOT NULL DEFAULT 0 AFTER estado',
    'DO 0'
);
PREPARE st_col FROM @sql_col;
EXECUTE st_col;
DEALLOCATE PREPARE st_col;

-- Backfill: los pedidos que YA estaban cancelados antes de esta migración nunca tuvieron
-- su stock repuesto (el código viejo no lo hacía). Se marcan como stock_repuesto = 0 a
-- propósito (es el default), para que si un admin los reactiva y vuelve a cancelar, el
-- nuevo código sí repone correctamente esa vez. No se intenta reponer retroactivamente el
-- stock de pedidos cancelados en el pasado — sería adivinar sobre datos históricos.
