-- Direcciones de envío guardadas por usuario. Se usan para autollenar el checkout.
-- Idempotente: se puede correr varias veces sin efecto adverso.
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
