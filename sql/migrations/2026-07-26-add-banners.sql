-- Migración: tabla de banners/promociones para el carrusel del hero.
-- Ejecutar en bases de datos ya desplegadas; en instalaciones nuevas ya viene en schema.sql.
CREATE TABLE IF NOT EXISTS banners (
    id          INT UNSIGNED      AUTO_INCREMENT PRIMARY KEY,
    titulo      VARCHAR(150)      NULL,
    imagen      VARCHAR(255)      NOT NULL,
    enlace      VARCHAR(500)      NULL,
    orden       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    activo      TINYINT(1)        NOT NULL DEFAULT 1,
    created_at  DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_banners_activo (activo),
    KEY idx_banners_orden  (orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
