# Seguridad de la base de datos — DS/YOWI

Guía operativa para mantener la base de datos protegida en producción (Hostinger/MariaDB).

## 1. Usuario de aplicación con privilegios mínimos

La app (endpoints en `site/api/`) **solo hace DML** en tiempo de ejecución
(SELECT/INSERT/UPDATE/DELETE). No necesita —ni debe tener— privilegios de DDL.

- Crear los usuarios con `sql/provision-db-user.sql` (rellena contraseñas fuertes):
  - `ds_app` → SOLO `SELECT, INSERT, UPDATE, DELETE`. Es el de `env.php` en producción.
  - `ds_migrator` → con DDL, para aplicar `sql/schema.sql` y migraciones **a mano**.
- En `site/api/config/env.php` de producción usar `ds_app` (nunca el usuario con DDL).

**Verificado**: con `ds_app`, la app funciona completa (login, catálogo, favoritos,
direcciones, creación de pedido) pero `DROP`/`ALTER`/`CREATE` fallan con error 1142.
Beneficio: una inyección o credencial filtrada no puede destruir/alterar la estructura.

### Aplicar migraciones en producción
Con el usuario `ds_migrator` (no la app):
```
mysql --user=ds_migrator -p ds_sports_supplements < sql/migrations/ARCHIVO.sql
```
`scripts/setup-local.php` sigue sirviendo para desarrollo local.

## 2. Respaldos automáticos

- Script: `scripts/backup-db.sh` (mysqldump `--single-transaction` + gzip + rotación).
- Programar en el cron de Hostinger (diario), guardando **fuera del docroot**:
  ```
  15 3 * * * DB_USER=ds_backup DB_PASS=... /usr/bin/bash /ruta/scripts/backup-db.sh >> /ruta/backups/backup.log 2>&1
  ```
- Recomendado un usuario `ds_backup` con solo `SELECT, LOCK TABLES` para los dumps.
- Retención por defecto: 7 diarios + 4 semanales (configurable con `KEEP_DAILY`/`KEEP_WEEKLY`).
- **Probar la restauración** periódicamente:
  ```
  gunzip -c backup.sql.gz | mysql --user=ds_migrator -p ds_restore_test
  ```
  **Verificado** en local: dump de 12 tablas restaurado íntegro en una BD limpia.

## 3. Estado ya correcto (no tocar)

- PDO con `ATTR_EMULATE_PREPARES => false` y **prepared statements** en todas las
  consultas (`site/api/config/database.php`). Sin concatenación de input en SQL.
- `charset=utf8mb4`. Errores PDO capturados por `set_exception_handler` sin filtrar
  stack traces (`site/api/lib/Response.php`).
- Integridad referencial con FKs y `ON DELETE` correctos (`sql/schema.sql`).
- `env.php` **gitignored** y bloqueado por `site/api/config/.htaccess`.

## 4. Checklist de despliegue seguro (BD)

- [ ] `env.php` de producción usa `ds_app` con contraseña fuerte y única.
- [ ] Migraciones aplicadas con `ds_migrator`, no con la app.
- [ ] Cron de respaldo activo y probado (restauración validada).
- [ ] Respaldos guardados fuera del docroot, con permisos `600`.
- [ ] Si `.git`/`sql/` estuvieron alguna vez accesibles por web, **rotar** la
      contraseña de la BD (pudo filtrarse).
