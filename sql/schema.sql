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
    created_at    DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_admins_email (email)
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
    nombre           VARCHAR(255)      NOT NULL,
    marca            VARCHAR(120)      NOT NULL,
    category_id      INT UNSIGNED      NOT NULL,
    cantidad         VARCHAR(80)       NOT NULL DEFAULT '',
    descripcion      TEXT              NULL,
    precio           DECIMAL(10,2)     NOT NULL DEFAULT 0.00,
    precio_original  DECIMAL(10,2)     NULL,
    stock            SMALLINT UNSIGNED NOT NULL DEFAULT 0,
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

-- ── Usuarios (sin cambios) ────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre        VARCHAR(150) NOT NULL,
    email         VARCHAR(190) NOT NULL,
    telefono      VARCHAR(30)  NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Pedidos (sin cambios funcionales) ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS orders (
    id               INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    user_id          INT UNSIGNED  NULL,
    nombre_cliente   VARCHAR(150)  NOT NULL,
    ciudad           VARCHAR(120)  NULL,
    telefono         VARCHAR(30)   NULL,
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
    nombre_producto VARCHAR(255)      NOT NULL,
    precio_unitario DECIMAL(10,2)     NOT NULL,
    cantidad        SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    subtotal        DECIMAL(10,2)     NOT NULL,
    CONSTRAINT fk_items_order   FOREIGN KEY (order_id)    REFERENCES orders(id)   ON DELETE CASCADE,
    CONSTRAINT fk_items_product FOREIGN KEY (producto_id) REFERENCES products(id) ON DELETE SET NULL,
    KEY idx_items_order (order_id)
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
