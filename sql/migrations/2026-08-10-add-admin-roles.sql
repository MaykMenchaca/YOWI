-- Añade el sistema de roles del panel admin: hasta ahora `admins` no tenía columna de
-- rol, así que todo admin que existiera era automáticamente "dueño" con control total.
-- Con esto, el dueño puede crear cuentas de empleados con acceso acotado (Operador o
-- Solo lectura) desde el panel, en vez de compartir su propia contraseña.
--
-- rol: VARCHAR (no ENUM) para poder añadir un rol futuro sin necesitar un ALTER — la
-- whitelist real vive en PHP (ds_rol_nivel() en AdminSession.php). Default 'lectura' a
-- propósito: es el rol MENOS privilegiado, así que si algún código nuevo alguna vez
-- olvida asignar rol explícito, la cuenta creada es la más inofensiva, no un dueño.
--
-- activo: soporta la baja de un empleado (desactivar, no borrar) sin perder su historial
-- en admin_audit_log — decisión del usuario.
--
-- password_changed_at: espejo exacto de users.password_changed_at (ver
-- 2026-08-02-password-changed-at.sql), para poder cerrar la sesión de un admin cuando
-- se le resetea la contraseña o cambia la suya.
--
-- Idempotente y portable (MariaDB + MySQL 8): information_schema + PREPARE/EXECUTE,
-- mismo patrón que el resto de migraciones de este proyecto.

SET @existe_rol := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admins' AND COLUMN_NAME = 'rol'
);
SET @sql_rol := IF(@existe_rol = 0,
    "ALTER TABLE admins ADD COLUMN rol VARCHAR(20) NOT NULL DEFAULT 'lectura' AFTER email",
    'DO 0'
);
PREPARE st_rol FROM @sql_rol;
EXECUTE st_rol;
DEALLOCATE PREPARE st_rol;

SET @existe_activo := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admins' AND COLUMN_NAME = 'activo'
);
SET @sql_activo := IF(@existe_activo = 0,
    'ALTER TABLE admins ADD COLUMN activo TINYINT(1) NOT NULL DEFAULT 1 AFTER rol',
    'DO 0'
);
PREPARE st_activo FROM @sql_activo;
EXECUTE st_activo;
DEALLOCATE PREPARE st_activo;

SET @existe_pwd := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admins' AND COLUMN_NAME = 'password_changed_at'
);
SET @sql_pwd := IF(@existe_pwd = 0,
    'ALTER TABLE admins ADD COLUMN password_changed_at DATETIME NULL AFTER activo',
    'DO 0'
);
PREPARE st_pwd FROM @sql_pwd;
EXECUTE st_pwd;
DEALLOCATE PREPARE st_pwd;

-- Migrar a TODOS los admins que ya existan a "dueno". Es la dirección segura: nadie
-- pierde acceso a su propio panel al desplegar esto. Si el dueño real quiere degradar a
-- alguna cuenta, lo hace desde la UI nueva después. Lo contrario (dejarlos en 'lectura',
-- el default de la columna) dejaría al dueño fuera de su propio negocio.
UPDATE admins SET rol = 'dueno' WHERE rol = 'lectura';
