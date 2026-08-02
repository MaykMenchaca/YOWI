-- Registrar cuándo se cambió por última vez la contraseña de un usuario, para
-- invalidar sesiones abiertas anteriores a un reset. Idempotente (IF NOT EXISTS).
ALTER TABLE users ADD COLUMN IF NOT EXISTS password_changed_at DATETIME NULL AFTER password_hash;
