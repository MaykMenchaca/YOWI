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
-- Idempotente: ADD COLUMN IF NOT EXISTS y la creación del índice tolera repetición.

ALTER TABLE products ADD COLUMN IF NOT EXISTS sku VARCHAR(64) NULL AFTER id;

-- El índice único se crea aparte para poder ignorarlo si ya existe (setup-local envuelve
-- cada sentencia en try/catch).
ALTER TABLE products ADD UNIQUE KEY uq_products_sku (sku);
