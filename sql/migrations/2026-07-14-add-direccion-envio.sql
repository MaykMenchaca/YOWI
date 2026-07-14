-- Migración: agrega la columna direccion_envio a orders (checkout con envío a domicilio).
-- Ejecutar en bases de datos ya desplegadas; en instalaciones nuevas ya viene en schema.sql.
-- MariaDB soporta IF NOT EXISTS; en MySQL 8 quita "IF NOT EXISTS" si la columna aún no existe.
ALTER TABLE orders
  ADD COLUMN IF NOT EXISTS direccion_envio TEXT NULL AFTER telefono;
