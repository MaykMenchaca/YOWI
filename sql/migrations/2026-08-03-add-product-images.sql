-- Galería de varias imágenes por producto (F4.1).
--
-- products.imagen se conserva tal cual como imagen principal: nada de lo que ya lee esa
-- columna (catálogo, favoritos, carrito, pedidos) se rompe. Esta tabla es solo la galería
-- adicional. ON DELETE CASCADE: borrar un producto borra su galería.

CREATE TABLE IF NOT EXISTS product_images (
    id         INT UNSIGNED      AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED      NOT NULL,
    url        VARCHAR(255)      NOT NULL,
    orden      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_images_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    KEY idx_images_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
