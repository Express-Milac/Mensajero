#!/usr/bin/env bash
# Fase 2 protegida: preserva login corporativo y layout existente.
set -Eeuo pipefail
umask 077
origen="$(cd "$1" && pwd)"
cd /home/sistemas/laravelProjects/Mensajero
original="$origen/instalar-fase2.sh"
test -f "$original" || { echo 'ERROR=INSTALADOR_NO_EXISTE' >&2; exit 1; }
printf '%s  %s\n' '5f096000dea48f0da3c703d54d7cd9b4679bedaf24ad456a6c877ea0a1a6f8bc' "$original" | sha256sum -c - >/dev/null || {
  echo 'ERROR=VERSION_DEL_INSTALADOR_NO_ESPERADA' >&2; exit 1;
}
modo="$(php artisan tinker --execute='echo config("mensajero.autenticacion.modo");')"
test "$modo" = 'corporativo' || { echo 'ERROR=AUTENTICACION_NO_CORPORATIVA' >&2; exit 1; }

lista="$(mktemp /tmp/mensajero-fase2-protegidos.XXXXXX)"
huellas="$(mktemp /tmp/mensajero-fase2-huellas.XXXXXX)"
rutas="$(mktemp /tmp/mensajero-fase2-rutas.XXXXXX)"
instalador="$(mktemp "$origen/.instalar-corporativo.XXXXXX")"
limpiar() { rm -f "$lista" "$huellas" "$rutas" "$instalador"; }
trap limpiar EXIT
cat > "$lista" <<'FILES'
app/Http/Controllers/Autenticacion/ControladorSesion.php
app/Http/Controllers/Autenticacion/ControladorAccesoCorporativo.php
app/Services/Autenticacion/AutenticadorCorporativo.php
app/Http/Middleware/RequerirAccesoMensajero.php
config/auth.php
config/mensajero.php
resources/views/components/layout.blade.php
FILES
while IFS= read -r archivo; do
  test -f "$archivo" || { echo "ERROR=FALTA_ARCHIVO:$archivo" >&2; exit 1; }
  sha256sum "$archivo" >> "$huellas"
done < "$lista"
php artisan route:list --name=acceso > "$rutas"
grep -Fq 'acceso.corporativo' "$rutas" || { echo 'ERROR=NO_EXISTE_SSO' >&2; exit 1; }

# Crear una COPIA temporal del instalador original sin cambios de navegación.
python3 - "$original" "$instalador" <<'PY'
from pathlib import Path
import re
import sys
contenido = Path(sys.argv[1]).read_text()
contenido, n = re.subn(r'(?m)^[ \t]*resources/views/components/layout\.blade\.php \\\n', '', contenido)
if n != 2:
    raise SystemExit('ERROR=BACKUP_LAYOUT_INESPERADO')
linea = 'php "$origen/parchar-navegacion.php" resources/views/components/layout.blade.php\n'
if contenido.count(linea) != 1:
    raise SystemExit('ERROR=PARCHE_LAYOUT_INESPERADO')
Path(sys.argv[2]).write_text(contenido.replace(linea, ''))
PY
bash -n "$instalador"
echo 'LOGIN_CORPORATIVO=PROTEGIDO'
echo 'MENU_EXISTENTE=NO_SE_MODIFICARA'
bash "$instalador"

echo '=== VALIDAR QUE EL LOGIN NO CAMBIO ==='
sha256sum -c "$huellas"
rutas_despues="$(mktemp /tmp/mensajero-fase2-rutas-despues.XXXXXX)"
trap 'rm -f "$lista" "$huellas" "$rutas" "$instalador" "$rutas_despues"' EXIT
php artisan route:list --name=acceso > "$rutas_despues"
cmp -s "$rutas" "$rutas_despues" || {
  echo 'ERROR=RUTAS_SSO_CAMBIARON_REVISAR' >&2; exit 1;
}
echo 'ARCHIVOS_LOGIN=IDENTICOS'
echo 'RUTAS_ACCESO_Y_SSO=IDENTICAS'
echo 'MENU_CORPORATIVO=IDENTICO'
echo 'GESTOR_WHATSAPP=NO_INICIADO'
