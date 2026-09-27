#!/usr/bin/env bash
# Pruebas de seguridad no destructivas contra el sitio publicado.
# Uso: bash qa/security_checks.sh [https://honshitsudojo.com.ar]
# No modifica datos: las acciones del panel se envían sin sesión o con un token inválido.
set -u
B="${1:-https://honshitsudojo.com.ar}"
FAIL=0
pass() { printf '  OK   %s\n' "$1"; }
fail() { printf '  FALLA %s\n' "$1"; FAIL=1; }
code() { curl -s -o /dev/null -w '%{http_code}' "$@"; }

echo "== HTTPS"
[ "$(code "http://${B#https://}/")" = "301" ] && pass "http redirige a https" || fail "http no redirige a https"

echo "== Archivos privados"
for p in .env .git/config content.py app.py deploy.py requirements.txt \
         admin/config.php admin/credentials.php admin/bootstrap.php admin/.htaccess templates/index.html; do
  c=$(code "$B/$p")
  case "$c" in 403|404|406) pass "$p -> $c" ;; *) fail "$p -> $c (debería estar bloqueado)" ;; esac
done

echo "== Listado de carpetas"
for d in gallery/ gallery/media/ collage/media/ blog/files/ ubicacion/; do
  c=$(code "$B/$d")
  [ "$c" = "403" ] && pass "$d sin listado" || fail "$d -> $c"
done

echo "== Panel de administración"
J=$(mktemp)
body=$(curl -s -X POST -d "action=gallery_delete&id=x" "$B/admin/index.php")
echo "$body" | grep -q 'name="password"' && pass "POST sin sesión no ejecuta acciones" || fail "POST sin sesión"
body=$(curl -s -c "$J" -b "$J" -d "action=login&user=qa-invalido&password=qa-invalido" "$B/admin/index.php")
echo "$body" | grep -q "incorrectos" && pass "credenciales inválidas rechazadas" || fail "login inválido"
body=$(curl -s -c "$J" -b "$J" "$B/admin/index.php")
echo "$body" | grep -qi "contraseña <strong>" && fail "la pantalla de ingreso muestra la contraseña" || pass "la pantalla de ingreso no muestra credenciales"
rm -f "$J"

echo "== Encabezados"
curl -sI "$B/" | grep -iE '^(strict-transport-security|x-content-type-options|x-frame-options|referrer-policy|content-security-policy):' \
  || echo "  (sin encabezados de seguridad adicionales)"

exit $FAIL
