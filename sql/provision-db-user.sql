-- ─────────────────────────────────────────────────────────────────────────────
-- Provisión de usuarios de base de datos con PRIVILEGIOS MÍNIMOS.
--
-- Objetivo: que el usuario con el que se conecta la APLICACIÓN (el de env.php) NO
-- pueda ejecutar DDL (DROP, ALTER, CREATE, TRUNCATE) ni GRANT. Así, aunque hubiera
-- una inyección o una credencial filtrada, el daño se limita a los datos (que se
-- recuperan del respaldo), no a la estructura ni a otros esquemas.
--
-- Se definen DOS usuarios:
--   1) ds_app       → SOLO SELECT/INSERT/UPDATE/DELETE. Es el de env.php (producción).
--   2) ds_migrator  → con DDL, para correr migraciones/DDL MANUALMENTE (no lo usa la app).
--
-- IMPORTANTE:
--   - Reemplaza 'CAMBIA_ESTE_PASSWORD_FUERTE' por contraseñas ROBUSTAS y ÚNICAS.
--   - En Hostinger, ajusta el host: normalmente 'localhost' (o el que indique hPanel).
--   - NO versionar este archivo con las contraseñas reales rellenadas.
-- ─────────────────────────────────────────────────────────────────────────────

-- Ajusta el nombre del esquema si difiere.
-- (En Hostinger suele ser algo como u123456789_dssupp).

-- 1) Usuario de la APLICACIÓN — mínimos privilegios (solo DML) ------------------
CREATE USER IF NOT EXISTS 'ds_app'@'localhost' IDENTIFIED BY 'CAMBIA_ESTE_PASSWORD_FUERTE';
GRANT SELECT, INSERT, UPDATE, DELETE
    ON `ds_sports_supplements`.*
    TO 'ds_app'@'localhost';
-- NADA de DROP/ALTER/CREATE/INDEX/GRANT/TRUNCATE/FILE/PROCESS.

-- 2) Usuario MIGRADOR — para DDL manual (schema.sql y migraciones) --------------
--    Úsalo SOLO tú, a mano, al aplicar cambios de estructura. La app nunca lo usa.
CREATE USER IF NOT EXISTS 'ds_migrator'@'localhost' IDENTIFIED BY 'CAMBIA_ESTE_PASSWORD_FUERTE_2';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, INDEX, REFERENCES
    ON `ds_sports_supplements`.*
    TO 'ds_migrator'@'localhost';

FLUSH PRIVILEGES;

-- ─────────────────────────────────────────────────────────────────────────────
-- Después de crear ds_app, en site/api/config/env.php de PRODUCCIÓN usa:
--   'DB_USER' => 'ds_app',
--   'DB_PASS' => '<la contraseña fuerte de ds_app>',
-- Verifica que la app funciona (login, catálogo, pedido, favoritos, direcciones)
-- y que ds_app NO puede: DROP TABLE, ALTER TABLE, CREATE TABLE (deben fallar).
-- ─────────────────────────────────────────────────────────────────────────────
