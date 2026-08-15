-- Consentimiento que sí queda registrado (Fase 5 del plan de info-negocio-editable).
--
-- Hasta ahora el checkbox de "Acepto los términos..." del registro solo bloqueaba el
-- envío EN EL NAVEGADOR (assets/js/auth.js) — un POST directo a api/auth/register.php
-- creaba la cuenta sin marcar nada, y ni siquiera se guardaba la fecha de quien sí lo
-- aceptó desde la UI. Lo mismo en el checkout de invitado: no se pedía consentimiento
-- para transferir nombre/teléfono/domicilio a WhatsApp. Estas dos columnas guardan el
-- momento real en que cada quien aceptó, no solo que el formulario lo permitió enviar.
--
-- Idempotente y portable (MariaDB + MySQL 8): information_schema + PREPARE/EXECUTE,
-- mismo patrón que 2026-08-09-add-orders-stock-repuesto.sql.

SET @existe_col := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'terms_accepted_at'
);
SET @sql_col := IF(@existe_col = 0,
    'ALTER TABLE users ADD COLUMN terms_accepted_at DATETIME NULL AFTER email_verified',
    'DO 0'
);
PREPARE st_col FROM @sql_col;
EXECUTE st_col;
DEALLOCATE PREPARE st_col;

SET @existe_col2 := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'privacidad_aceptada_at'
);
SET @sql_col2 := IF(@existe_col2 = 0,
    'ALTER TABLE orders ADD COLUMN privacidad_aceptada_at DATETIME NULL AFTER mensaje_whatsapp',
    'DO 0'
);
PREPARE st_col2 FROM @sql_col2;
EXECUTE st_col2;
DEALLOCATE PREPARE st_col2;

-- Backfill: las cuentas y pedidos que ya existían antes de esta migración se quedan con
-- NULL a propósito — no se puede afirmar retroactivamente que alguien aceptó algo que en
-- ese momento el sistema ni siquiera pedía. Solo los registros/pedidos nuevos, hechos ya
-- con el checkbox obligatorio del lado del servidor, tendrán la fecha real.
