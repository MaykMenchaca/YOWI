-- Migración: tabla de marcas (brands) gestionables desde el admin.
-- Ejecutar en bases de datos ya desplegadas; en instalaciones nuevas ya viene en schema.sql.
CREATE TABLE IF NOT EXISTS brands (
    id          INT UNSIGNED      AUTO_INCREMENT PRIMARY KEY,
    nombre      VARCHAR(120)      NOT NULL,
    slug        VARCHAR(140)      NOT NULL,
    imagen      VARCHAR(255)      NULL,
    enlace      VARCHAR(500)      NULL,
    orden       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    activo      TINYINT(1)        NOT NULL DEFAULT 1,
    created_at  DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_brands_slug (slug),
    KEY idx_brands_activo (activo),
    KEY idx_brands_orden  (orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
