#!/usr/bin/env bash
# Mensajero Milac: agrega el acceso administrativo al menú sin sustituir el layout corporativo.
set -Eeuo pipefail
umask 077
raiz='/home/sistemas/laravelProjects/Mensajero'
fuente="$(cd "$(dirname "$0")/.." && pwd)/resources/views/components/partes/navegacion-administracion-whatsapp.blade.php"
cd "$raiz"

layout='resources/views/components/layout.blade.php'
parcial='resources/views/components/partes/navegacion-administracion-whatsapp.blade.php'
ancla="@include('components.partes.navegacion-administracion-whatsapp')"

for archivo in "$layout" 'routes/web.php' 'app/Http/Middleware/RequerirAccesoMensajero.php'; do
    test -f "$archivo" && test ! -L "$archivo" || { echo "ERROR=ARCHIVO_REQUERIDO:$archivo" >&2; exit 1; }
done
test -f "$fuente" || { echo 'ERROR=FALTA_PARCIAL' >&2; exit 1; }
test ! -L "$(dirname "$parcial")" || { echo 'ERROR=RUTA_PARCIAL_SIMBOLICA' >&2; exit 1; }
if [ -e "$parcial" ] || [ -L "$parcial" ]; then
    echo 'ERROR=PARCIAL_EXISTENTE_NO_SE_SOBRESCRIBE' >&2
    exit 1
fi
if grep -Fq "$ancla" "$layout"; then
    echo 'ERROR=ENLACE_YA_REGISTRADO' >&2
    exit 1
fi
if ! php artisan route:list --path=administracion/whatsapp | grep -Fq 'whatsapp.conexiones.indice'; then
    echo 'ERROR=RUTA_ADMINISTRATIVA_NO_DISPONIBLE' >&2
    exit 1
fi

# Comprobar el ancla del menú ANTES de tocar el disco. No sustituir contenido SSO.
python3 - "$layout" <<'PY'
from pathlib import Path
import sys
contenido=Path(sys.argv[1]).read_text(encoding='utf-8')
if contenido.count('<nav class="navigation">') != 1 or contenido.count('</nav>') != 1:
    raise SystemExit('ERROR=ESTRUCTURA_DEL_MENU_INESPERADA')
if 'navegacion-administracion-whatsapp' in contenido:
    raise SystemExit('ERROR=ENLACE_YA_PRESENTE')
print('ESTRUCTURA_MENU=VALIDADA')
PY

respaldo="$(mktemp -d "$HOME/.mensajero-menu-whatsapp.XXXXXX")"
chmod 700 "$respaldo"
cp -p "$layout" "$respaldo/layout.blade.php"
echo "RESPALDO=$respaldo"

# Trabajar sobre archivo temporal en el mismo directorio y publicar con rename atómico.
temporal="$(mktemp "$(dirname "$layout")/.layout-whatsapp.XXXXXX")"
limpiar() { rm -f "$temporal"; }
trap limpiar EXIT
python3 - "$layout" "$temporal" <<'PY'
from pathlib import Path
import os
import stat
import sys
origen=Path(sys.argv[1]); destino=Path(sys.argv[2])
contenido=origen.read_text(encoding='utf-8')
import re
coincidencias=list(re.finditer(r'(?m)^(?P<indent>[ \t]*)</nav>[ \t]*$', contenido))
if len(coincidencias) != 1:
    raise SystemExit('ERROR=CIERRE_NAV_INESPERADO')
ubicacion=coincidencias[0]
insertar=ubicacion.group('indent') + "    @include('components.partes.navegacion-administracion-whatsapp')\n"
nuevo=contenido[:ubicacion.start()] + insertar + contenido[ubicacion.start():]
destino.write_text(nuevo, encoding='utf-8')
os.chmod(destino, stat.S_IMODE(origen.stat().st_mode))
PY

# Si la vista no admite compilación, abandonar sin modificar la navegación.
mkdir -p "$(dirname "$parcial")"
chmod 755 "$(dirname "$parcial")"
cp "$fuente" "$parcial"
chmod 644 "$parcial"

# Confirma que Blade pueda compilar el layout y el parcial. No reinicia servicios.
if ! php artisan tinker --execute='$b=app("blade.compiler"); $b->compileString(file_get_contents(resource_path("views/components/layout.blade.php"))); $b->compileString(file_get_contents(resource_path("views/components/partes/navegacion-administracion-whatsapp.blade.php"))); echo "BLADE=COMPILADO".PHP_EOL;' ; then
    rm -f "$parcial"
    exit 1
fi

mv "$temporal" "$layout"

# Prueba de integridad: sólo se añadió la inclusión, sin tocar el resto del layout.
python3 - "$respaldo/layout.blade.php" "$layout" <<'PY'
from pathlib import Path
import sys
antes=Path(sys.argv[1]).read_text(encoding='utf-8')
despues=Path(sys.argv[2]).read_text(encoding='utf-8')
import re
incidencias=list(re.finditer(r"(?m)^[ \t]*@include\('components\.partes\.navegacion-administracion-whatsapp'\)\n", despues))
assert len(incidencias)==1, 'ERROR=INCLUSION_INESPERADA'
inc=incidencias[0].group(0)
assert despues.replace(inc, '', 1) == antes, 'ERROR=CAMBIO_ADICIONAL_DETECTADO'
print('LAYOUT_ORIGINAL=CONSERVADO')
PY

echo 'MENU_ADMINISTRACION_WHATSAPP=INSTALADO'
echo 'SSO_CORPORATIVO=NO_MODIFICADO'
echo 'GESTOR_WHATSAPP=NO_REINICIADO'
