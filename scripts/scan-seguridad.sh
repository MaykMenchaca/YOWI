#!/usr/bin/env bash
# Escáner de seguridad del proyecto. Corre en un comando:
#   1. semgrep con las reglas de .semgrep.yml (funciones peligrosas, SQL concatenado,
#      open redirect, eval/new Function).
#   2. Dos comprobaciones ESTRUCTURALES que un patrón AST de semgrep no expresa bien
#      ("¿esta llamada aparece en ALGÚN lugar del archivo?" es justo lo que un grep
#      hace mejor que una regla semgrep — se intentó como regla y salió frágil, ver
#      la nota en .semgrep.yml): todo endpoint admin debe llamar a ds_require_admin(),
#      y todo endpoint admin que acepta POST debe validar CSRF.
#
# Uso: scripts/scan-seguridad.sh
# Sale con código 1 si algo falla (útil para CI).

set -euo pipefail
cd "$(dirname "$0")/.."

FALLO=0

echo "== 1/3: semgrep (.semgrep.yml) =="
if command -v semgrep >/dev/null 2>&1; then
  semgrep --config .semgrep.yml site/ scripts/ --quiet || FALLO=1
else
  echo "  semgrep no está instalado (pip install semgrep). Saltando este paso."
fi

echo
echo "== 2/3: todo endpoint bajo site/api/admin/ debe llamar a ds_require_admin() =="
# Excepciones deliberadas, no bugs:
#   - login.php: es donde se CREA la sesión, no puede exigirla antes.
#   - logout.php: debe poder cerrar la sesión aunque el admin no tenga 2FA enrolado
#     (ds_require_admin() por defecto ahora exige 2FA — logout no debe depender de eso).
#   - me.php: bootstrap de sesión; responde admin:null a propósito si no hay sesión,
#     el front lo usa precisamente para PREGUNTAR si hay sesión, no para exigirla.
while IFS= read -r f; do
  case "$f" in
    site/api/admin/auth/login.php|site/api/admin/auth/logout.php|site/api/admin/auth/me.php) continue ;;
  esac
  if ! grep -q 'ds_require_admin(' "$f"; then
    echo "  FALTA: $f no llama a ds_require_admin()"
    FALLO=1
  fi
done < <(find site/api/admin -name '*.php')

echo
echo "== 3/3: todo endpoint admin que acepta POST debe validar CSRF =="
while IFS= read -r f; do
  if grep -q "'POST'" "$f"; then
    if ! grep -q 'ds_admin_csrf_check(' "$f"; then
      echo "  FALTA: $f acepta POST pero no llama a ds_admin_csrf_check()"
      FALLO=1
    fi
  fi
done < <(find site/api/admin -name '*.php')

echo
if [ "$FALLO" -eq 0 ]; then
  echo "OK: todo en verde."
else
  echo "Hay hallazgos arriba."
  exit 1
fi
