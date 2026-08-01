#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# Respaldo automático de la base de datos DS/YOWI con rotación.
#
# Uso (manual):   bash scripts/backup-db.sh
# Uso (cron Hostinger, diario 03:15):
#   15 3 * * * /usr/bin/bash /home/USUARIO/ruta/scripts/backup-db.sh >> /home/USUARIO/backups/backup.log 2>&1
#
# Requisitos:
#   - mysqldump disponible en el PATH.
#   - Credenciales por variables de entorno (NO hardcodear). Exporta antes de correr,
#     o defínelas en el propio cron. Recomendado un usuario de BD de SOLO LECTURA para
#     respaldos (SELECT, LOCK TABLES).
#
# Variables (con valores por defecto de ejemplo):
#   DB_HOST (localhost)  DB_NAME (ds_sports_supplements)  DB_USER  DB_PASS
#   BACKUP_DIR  (por defecto: <repo>/../backups, FUERA del docroot)
#   KEEP_DAILY (7)   KEEP_WEEKLY (4)
# ─────────────────────────────────────────────────────────────────────────────
set -euo pipefail

DB_HOST="${DB_HOST:-localhost}"
DB_NAME="${DB_NAME:-ds_sports_supplements}"
DB_USER="${DB_USER:?Define DB_USER}"
DB_PASS="${DB_PASS:?Define DB_PASS}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# Por defecto, guardar FUERA del repo/docroot (un nivel arriba).
BACKUP_DIR="${BACKUP_DIR:-$SCRIPT_DIR/../../backups}"
KEEP_DAILY="${KEEP_DAILY:-7}"
KEEP_WEEKLY="${KEEP_WEEKLY:-4}"

mkdir -p "$BACKUP_DIR/daily" "$BACKUP_DIR/weekly"

# Cifrado opcional: si defines BACKUP_ENC_PASS, el respaldo se cifra con AES-256 y queda
# como .sql.gz.enc (recomendado si lo copias offsite). Sin esa variable, queda .sql.gz.
if [ -n "${BACKUP_ENC_PASS:-}" ]; then EXT="sql.gz.enc"; else EXT="sql.gz"; fi

STAMP="$(date +%Y-%m-%d_%H%M%S)"
OUT="$BACKUP_DIR/daily/${DB_NAME}_${STAMP}.${EXT}"

echo "[$(date)] Respaldando $DB_NAME -> $OUT"
# --single-transaction: dump consistente sin bloquear (InnoDB). --no-tablespaces evita
# necesitar el privilegio PROCESS. Comprimido con gzip y (opcional) cifrado.
if [ -n "${BACKUP_ENC_PASS:-}" ]; then
  mysqldump \
    --host="$DB_HOST" --user="$DB_USER" --password="$DB_PASS" \
    --single-transaction --quick --no-tablespaces \
    --routines --triggers --events \
    "$DB_NAME" | gzip -9 \
    | openssl enc -aes-256-cbc -pbkdf2 -salt -pass env:BACKUP_ENC_PASS -out "$OUT"
else
  mysqldump \
    --host="$DB_HOST" --user="$DB_USER" --password="$DB_PASS" \
    --single-transaction --quick --no-tablespaces \
    --routines --triggers --events \
    "$DB_NAME" | gzip -9 > "$OUT"
fi

# Permisos restrictivos (el dump contiene todos los datos).
chmod 600 "$OUT"

# El respaldo del domingo se archiva como semanal.
if [ "$(date +%u)" = "7" ]; then
  cp -p "$OUT" "$BACKUP_DIR/weekly/${DB_NAME}_${STAMP}.${EXT}"
fi

# Rotación: conservar los últimos N diarios y N semanales.
ls -1t "$BACKUP_DIR/daily/"*."${EXT}"  2>/dev/null | tail -n +"$((KEEP_DAILY+1))"  | xargs -r rm -f
ls -1t "$BACKUP_DIR/weekly/"*."${EXT}" 2>/dev/null | tail -n +"$((KEEP_WEEKLY+1))" | xargs -r rm -f

echo "[$(date)] Respaldo OK. Diarios: $(ls -1 "$BACKUP_DIR/daily" | wc -l), Semanales: $(ls -1 "$BACKUP_DIR/weekly" | wc -l)"
if [ -n "${BACKUP_ENC_PASS:-}" ]; then
  echo "Restaurar:  openssl enc -d -aes-256-cbc -pbkdf2 -pass env:BACKUP_ENC_PASS -in $OUT | gunzip | mysql --host=HOST --user=USER -p $DB_NAME"
else
  echo "Restaurar:  gunzip -c $OUT | mysql --host=HOST --user=USER -p $DB_NAME"
fi
