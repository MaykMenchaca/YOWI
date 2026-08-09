-- DS Sports Supplements — esquema de base de datos (MySQL/MariaDB)
-- Importar una sola vez vía phpMyAdmin en Hostinger. NUNCA se sube por FTP a public_html.
-- v2: agrega admins, categories, products y migra order_items.producto_id a FK INT.

SET NAMES utf8mb4;

-- ── Administradores (tabla separada de users, sesión $_SESSION['admin_id']) ──────
CREATE TABLE IF NOT EXISTS admins (
    id            INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    nombre        VARCHAR(150)   NOT NULL,
    email         VARCHAR(190)   NOT NULL,
    password_hash VARCHAR(255)   NOT NULL,
    totp_secret   VARCHAR(64)    NULL,
    totp_enabled  TINYINT(1)     NOT NULL DEFAULT 0,
    created_at    DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_admins_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Códigos de recuperación del 2FA de admin (respaldo del autenticador) ──────────
CREATE TABLE IF NOT EXISTS admin_recovery_codes (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id   INT UNSIGNED NOT NULL,
    code_hash  CHAR(64)     NOT NULL,
    used_at    DATETIME     NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_recovery_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE,
    KEY idx_recovery_admin (admin_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Log de auditoría de acciones del administrador ────────────────────────────────
CREATE TABLE IF NOT EXISTS admin_audit_log (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id   INT UNSIGNED NULL,
    accion     VARCHAR(80)  NOT NULL,
    detalle    VARCHAR(255) NULL,
    ip         VARCHAR(45)  NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL,
    KEY idx_audit_admin (admin_id),
    KEY idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Categorías ───────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS categories (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre     VARCHAR(100) NOT NULL,
    slug       VARCHAR(110) NOT NULL,
    orden      SMALLINT     NOT NULL DEFAULT 0,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_categories_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Productos (reemplaza al catálogo JSON) ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS products (
    id               INT UNSIGNED      AUTO_INCREMENT PRIMARY KEY,
    -- Identidad estable del producto para reimportar el CSV sin duplicar (NULL permitido:
    -- varios NULL no colisionan en un UNIQUE, así conviven los aún sin SKU).
    sku              VARCHAR(64)       NULL,
    nombre           VARCHAR(255)      NOT NULL,
    marca            VARCHAR(120)      NOT NULL,
    category_id      INT UNSIGNED      NOT NULL,
    cantidad         VARCHAR(80)       NOT NULL DEFAULT '',
    unidad           VARCHAR(20)       NULL,
    descripcion      TEXT              NULL,
    precio           DECIMAL(10,2)     NOT NULL DEFAULT 0.00,
    precio_original  DECIMAL(10,2)     NULL,
    stock            SMALLINT UNSIGNED NULL DEFAULT NULL,
    imagen           VARCHAR(255)      NOT NULL DEFAULT 'assets/img/producto-placeholder.svg',
    badge            VARCHAR(30)       NULL,
    destacado        TINYINT(1)        NOT NULL DEFAULT 0,
    activo           TINYINT(1)        NOT NULL DEFAULT 1,
    created_at       DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_products_category FOREIGN KEY (category_id)
        REFERENCES categories(id) ON DELETE RESTRICT,
    KEY idx_products_category (category_id),
    KEY idx_products_activo   (activo),
    KEY idx_products_destacado(destacado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Sabores por producto: nombre + stock propio + precio propio ───────────────────
-- stock NULL = sin control de inventario; precio NULL = usa el precio del producto.
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

-- ── Galería de imágenes por producto (products.imagen sigue siendo la principal) ──
CREATE TABLE IF NOT EXISTS product_images (
    id         INT UNSIGNED      AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED      NOT NULL,
    url        VARCHAR(255)      NOT NULL,
    orden      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_images_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    KEY idx_images_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Banners / promociones del hero (carrusel de la portada) ───────────────────────
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

-- ── Marcas (brands) gestionables desde el admin ───────────────────────────────────
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

-- ── Settings (contenido editable desde el admin, ej. la página "Nosotros") ────────
CREATE TABLE IF NOT EXISTS settings (
    clave      VARCHAR(60)  NOT NULL PRIMARY KEY,
    valor      TEXT         NULL,
    updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Usuarios (sin cambios) ────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre         VARCHAR(150) NOT NULL,
    email          VARCHAR(190) NOT NULL,
    telefono       VARCHAR(30)  NULL,
    password_hash  VARCHAR(255) NOT NULL,
    password_changed_at DATETIME NULL,
    email_verified TINYINT(1)   NOT NULL DEFAULT 0,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Tokens de verificación de email y recuperación de contraseña ──────────────
CREATE TABLE IF NOT EXISTS email_verifications (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    token_hash CHAR(64)     NOT NULL,
    expires_at DATETIME     NOT NULL,
    used_at    DATETIME     NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_emailver_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uq_emailver_token (token_hash),
    KEY idx_emailver_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    token_hash CHAR(64)     NOT NULL,
    expires_at DATETIME     NOT NULL,
    used_at    DATETIME     NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pwreset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uq_pwreset_token (token_hash),
    KEY idx_pwreset_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Favoritos por usuario (wishlist) ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS favorites (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_favorites_user    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
    CONSTRAINT fk_favorites_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY uq_favorites_user_product (user_id, product_id),
    KEY idx_favorites_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Direcciones de envío guardadas por usuario ────────────────────────────────────
CREATE TABLE IF NOT EXISTS user_addresses (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id          INT UNSIGNED NOT NULL,
    etiqueta         VARCHAR(60)  NULL,
    nombre           VARCHAR(150) NOT NULL,
    telefono         VARCHAR(30)  NULL,
    calle            VARCHAR(255) NOT NULL,
    colonia          VARCHAR(150) NULL,
    cp               VARCHAR(10)  NULL,
    ciudad           VARCHAR(120) NULL,
    estado           VARCHAR(120) NULL,
    referencias      VARCHAR(255) NULL,
    es_predeterminada TINYINT(1)  NOT NULL DEFAULT 0,
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_addresses_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    KEY idx_addresses_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Pedidos (sin cambios funcionales) ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS orders (
    id               INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    user_id          INT UNSIGNED  NULL,
    nombre_cliente   VARCHAR(150)  NOT NULL,
    ciudad           VARCHAR(120)  NULL,
    telefono         VARCHAR(30)   NULL,
    direccion_envio  TEXT          NULL,
    total            DECIMAL(10,2) NOT NULL,
    estado           ENUM('pendiente','confirmado','enviado','entregado','cancelado')
                                   NOT NULL DEFAULT 'pendiente',
    mensaje_whatsapp TEXT          NULL,
    created_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    KEY idx_orders_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Items de pedido (producto_id ahora FK a products, NULL si producto eliminado) ─
CREATE TABLE IF NOT EXISTS order_items (
    id              INT UNSIGNED      AUTO_INCREMENT PRIMARY KEY,
    order_id        INT UNSIGNED      NOT NULL,
    producto_id     INT UNSIGNED      NULL,
    sabor_id        INT UNSIGNED      NULL,
    nombre_producto VARCHAR(255)      NOT NULL,
    sabor           VARCHAR(80)       NULL,
    precio_unitario DECIMAL(10,2)     NOT NULL,
    cantidad        SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    subtotal        DECIMAL(10,2)     NOT NULL,
    CONSTRAINT fk_items_order   FOREIGN KEY (order_id)    REFERENCES orders(id)   ON DELETE CASCADE,
    CONSTRAINT fk_items_product FOREIGN KEY (producto_id) REFERENCES products(id) ON DELETE SET NULL,
    CONSTRAINT fk_items_flavor  FOREIGN KEY (sabor_id)    REFERENCES product_flavors(id) ON DELETE SET NULL,
    KEY idx_items_order (order_id),
    KEY idx_items_sabor (sabor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Rate limiting de logins (anti-fuerza bruta, cliente y admin) ──────────────────
CREATE TABLE IF NOT EXISTS login_attempts (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tipo       ENUM('cliente','admin') NOT NULL,
    email      VARCHAR(190) NOT NULL,
    ip         VARCHAR(45)  NOT NULL,
    exitoso    TINYINT(1)   NOT NULL DEFAULT 0,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_attempts_lookup (tipo, email, ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
