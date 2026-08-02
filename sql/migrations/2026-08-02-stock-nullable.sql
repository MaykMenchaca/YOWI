-- Hacer stock NULL-able (NULL = sin control de inventario / ilimitado).
-- Idempotente: MODIFY COLUMN se puede re-ejecutar sin efecto adverso.
ALTER TABLE products MODIFY COLUMN stock SMALLINT UNSIGNED NULL DEFAULT NULL;

-- =====================================================================
-- PASO ONE-SHOT (NO auto-aplicable) — NO incluir aquí como sentencia.
-- ---------------------------------------------------------------------
-- Antes de este cambio, un 0 significaba "sin control / ilimitado".
-- Tras el cambio, 0 = agotado. Para migrar los 0 heredados a NULL hay
-- que correr UNA sola vez, a mano, contra la BD:
--
--   UPDATE products SET stock = NULL WHERE stock = 0;
--
-- ⚠ NO agregar esa sentencia a este archivo ni a sql/migrations/: el
--   setup re-ejecuta cada .sql en cada arranque y volvería a convertir
--   en NULL los productos legítimamente agotados (stock = 0). Es una
--   migración de datos de un solo uso, ya aplicada el 2026-08-02.
-- =====================================================================
