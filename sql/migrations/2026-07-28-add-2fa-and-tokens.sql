-- Fase 6 de seguridad: 2FA de admin (TOTP) + tokens de verificación de email y
-- recuperación de contraseña. Idempotente.

-- ── 2FA (TOTP) para administradores ───────────────────────────────────────────
-- Las columnas se añaden con ALTER; el bloque se ignora si ya existen (setup-local
-- envuelve cada sentencia en try/catch).
ALTER TABLE admins ADD COLUMN totp_secret  VARCHAR(64) NULL;
ALTER TABLE admins ADD COLUMN totp_enabled TINYINT(1)  NOT NULL DEFAULT 0;

-- ── Verificación de email (opcional, se activa con FEATURE_EMAIL) ──────────────
ALTER TABLE users ADD COLUMN email_verified TINYINT(1) NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS email_verifications (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    token_hash CHAR(64)     NOT NULL,           -- SHA-256 del token (nunca en claro)
    expires_at DATETIME     NOT NULL,
    used_at    DATETIME     NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_emailver_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uq_emailver_token (token_hash),
    KEY idx_emailver_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Recuperación de contraseña ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS password_resets (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    token_hash CHAR(64)     NOT NULL,           -- SHA-256 del token
    expires_at DATETIME     NOT NULL,
    used_at    DATETIME     NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pwreset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uq_pwreset_token (token_hash),
    KEY idx_pwreset_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
