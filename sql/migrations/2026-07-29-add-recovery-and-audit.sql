-- Seguridad 2ª ronda: códigos de recuperación del 2FA + log de auditoría admin.
-- Idempotente (CREATE TABLE IF NOT EXISTS).

-- ── Códigos de recuperación del 2FA (respaldo si se pierde el autenticador) ────
-- Se guarda SOLO el hash (SHA-256) del código, nunca el código en claro.
CREATE TABLE IF NOT EXISTS admin_recovery_codes (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id   INT UNSIGNED NOT NULL,
    code_hash  CHAR(64)     NOT NULL,
    used_at    DATETIME     NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_recovery_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE,
    KEY idx_recovery_admin (admin_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Log de auditoría de acciones del administrador ────────────────────────────
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
