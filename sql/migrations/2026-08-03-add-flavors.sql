-- Sabores por producto: nombre + stock propio + precio propio (F3.1).
--
-- Reglas (coherentes con el modelo de inventario ya existente en products.stock):
--   - stock NULL = sin control de inventario (siempre disponible); 0 = agotado.
--   - precio NULL = usa el precio del producto; si trae valor, ese manda.
--   - Si el producto tiene sabores, el inventario se controla por sabor: el producto
--     se ve agotado solo cuando TODOS sus sabores lo están.
--
-- ON DELETE CASCADE: borrar un producto borra sus sabores (no tiene sentido dejarlos
-- huérfanos). UNIQUE(product_id, slug) evita dos sabores con el mismo nombre en el
-- mismo producto (ej. "Chocolate" duplicado).

CREATE TABLE IF NOT EXISTS product_flavors (
    id         INT UNSIGNED      AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED      NOT NULL,
    nombre     VARCHAR(80)       NOT NULL,
    slug       VARCHAR(90)       NOT NULL,
    stock      SMALLINT UNSIGNED NULL DEFAULT NULL,
    precio     DECIMAL(10,2)     NULL,
    orden      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    activo     TINYINT(1)        NOT NULL DEFAULT 1,
    created_at DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_flavors_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY uq_flavors_product_slug (product_id, slug),
    KEY idx_flavors_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Deja constancia de qué sabor se pidió (el precio cobrado ya vive en precio_unitario).
ALTER TABLE order_items ADD COLUMN IF NOT EXISTS sabor VARCHAR(80) NULL AFTER nombre_producto;
