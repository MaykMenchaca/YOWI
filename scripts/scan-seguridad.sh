#!/usr/bin/env bash
# Escáner de seguridad del proyecto. Corre en un comando:
#   1. semgrep con las reglas de .semgrep.yml (funciones peligrosas, SQL concatenado,
#      open redirect, eval/new Function).
#   2. Tres comprobaciones ESTRUCTURALES que un patrón AST no expresa bien ("¿esta
#      llamada aparece en ALGÚN lugar del archivo?" es justo lo que un grep hace mejor
#      que una regla semgrep — se intentó como regla y salió frágil, ver la nota en
#      .semgrep.yml): todo endpoint admin pasa por el guardián, todo endpoint de
#      NEGOCIO (fuera de auth/) declara su rol explícito con ds_require_rol() — no
#      basta ds_require_admin() a secas, así un endpoint nuevo no puede quedarse sin
#      permiso por olvido — y todo POST valida CSRF.
#
# Uso: scripts/scan-seguridad.sh
# Sale con código 1 si algo falla (útil para CI).

set -euo pipefail
cd "$(dirname "$0")/.."

FALLO=0

echo "== 1/4: semgrep (.semgrep.yml) =="
if command -v semgrep >/dev/null 2>&1; then
  semgrep --config .semgrep.yml site/ scripts/ --quiet || FALLO=1
else
  echo "  semgrep no está instalado (pip install semgrep). Saltando este paso."
fi

echo
echo "== 2/4: todo endpoint bajo site/api/admin/ pasa por el guardián =="
# Excepciones deliberadas, no bugs:
#   - login.php: es donde se CREA la sesión, no puede exigirla antes.
#   - logout.php: debe poder cerrar la sesión aunque el admin no tenga 2FA enrolado
#     (el guardián por defecto ahora exige 2FA — logout no debe depender de eso).
#   - me.php: bootstrap de sesión; responde admin:null a propósito si no hay sesión,
#     el front lo usa precisamente para PREGUNTAR si hay sesión, no para exigirla.
while IFS= read -r f; do
  case "$f" in
    site/api/admin/auth/login.php|site/api/admin/auth/logout.php|site/api/admin/auth/me.php) continue ;;
  esac
  if ! grep -qE 'ds_require_admin\(|ds_require_rol\(' "$f"; then
    echo "  FALTA: $f no llama a ds_require_admin() ni a ds_require_rol()"
    FALLO=1
  fi
done < <(find site/api/admin -name '*.php')

echo
echo "== 3/4: todo endpoint de NEGOCIO (fuera de admin/auth/) declara su rol con ds_require_rol() =="
# admin/auth/* son self-service (2FA propio, cambiar la propia contraseña) — ahí
# ds_require_admin() a secas es correcto, cualquier rol puede usarlos. Todo lo demás
# (productos, categorías, pedidos, respaldos, gestión de usuarios...) es negocio: debe
# declarar explícitamente qué rol mínimo hace falta, para que un endpoint nuevo no
# quede accesible a cualquier admin por simple olvido.
while IFS= read -r f; do
  case "$f" in
    site/api/admin/auth/*) continue ;;
  esac
  if ! grep -q 'ds_require_rol(' "$f"; then
    echo "  FALTA: $f no declara un rol mínimo con ds_require_rol()"
    FALLO=1
  fi
done < <(find site/api/admin -name '*.php' -not -path 'site/api/admin/auth/*')

echo
echo "== 4/4: todo endpoint admin que acepta POST debe validar CSRF =="
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
