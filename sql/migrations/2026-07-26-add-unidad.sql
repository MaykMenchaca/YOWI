-- Migración: columna 'unidad' de medida para productos (g, kg, caps, tabs, ml, pack...).
-- 'cantidad' guarda el número; 'unidad' la unidad. Se muestran juntos en la tienda.
-- Ejecutar en bases ya desplegadas; en instalaciones nuevas ya viene en schema.sql.
-- (El runner de migraciones tolera el error si la columna ya existe.)
ALTER TABLE products ADD COLUMN unidad VARCHAR(20) NULL AFTER cantidad;
