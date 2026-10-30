#!/usr/bin/env bash
# Mejora Fase 2: renombrar y eliminar conexiones nunca vinculadas.
# No modifica SSO, layout, routes/web.php, datos ni sesiones WhatsApp.
set -Eeuo pipefail
umask 077
raiz='/home/sistemas/laravelProjects/Mensajero'
origen="$(cd "$(dirname "$0")/.." && pwd)"
cd "$raiz"

vista='resources/views/administracion/whatsapp/indice.blade.php'
rutas='routes/administracion_whatsapp_gestion.php'
controlador='app/Http/Controllers/Administracion/ControladorEdicionConexionesWhatsapp.php'
rutas_nuevas='routes/administracion_whatsapp_edicion.php'
pruebas='tests/Funcional/EdicionConexionesWhatsappTest.php'

# Solo actualiza el contenido original de la Fase 2; nunca pisa cambios posteriores.
verificar() {
  local hash="$1" archivo="$2"
  printf '%s  %s\n' "$hash" "$archivo" | sha256sum -c - >/dev/null || {
    echo "ERROR=ARCHIVO_MODIFICADO_NO_SE_SOBRESCRIBE:$archivo" >&2
    exit 1
  }
}
verificar '92464fe83952c9032e726124fe7519a56e100bc3c9536f042a211f320f3d80fd' "$vista"
verificar 'd393f94b9df75ab6e843822881bbd067479620a6a997508066171e2ff0a83540' "$rutas"

test -f 'app/Services/WhatsApp/ClienteGestorWhatsapp.php' || {
  echo 'ERROR=FASE2_NO_INSTALADA' >&2; exit 1;
}
for nuevo in "$controlador" "$rutas_nuevas" "$pruebas"; do
  if [ -e "$nuevo" ] || [ -L "$nuevo" ]; then
    echo "ERROR=YA_EXISTE_NO_SE_SOBRESCRIBE:$nuevo" >&2; exit 1
  fi
  test -f "$origen/$nuevo" || { echo "ERROR=FALTA_ORIGEN:$nuevo" >&2; exit 1; }
done
test -f "$origen/$vista" || { echo 'ERROR=FALTA_VISTA_NUEVA' >&2; exit 1; }

# Verificaciones PHP antes de escribir cualquier cosa.
php -l "$origen/$controlador"
php -l "$origen/$rutas_nuevas"
php -l "$origen/$pruebas"

# Copias de las rutas y de la vista (no de credenciales).
respaldo="$(mktemp -d "$HOME/.mensajero-edicion-conexiones.XXXXXX")"
chmod 700 "$respaldo"
cp -p "$vista" "$respaldo/indice.blade.php"
cp -p "$rutas" "$respaldo/administracion_whatsapp_gestion.php"
echo "RESPALDO=$respaldo"

instalados=()
revertir() {
  local codigo=$?
  if [ "$codigo" -ne 0 ]; then
    cp -p "$respaldo/indice.blade.php" "$vista"
    cp -p "$respaldo/administracion_whatsapp_gestion.php" "$rutas"
    for archivo in "${instalados[@]}"; do rm -f "$archivo"; done
    echo 'ERROR=INSTALACION_REVERTIDA' >&2
  fi
}
trap revertir EXIT

for nuevo in "$controlador" "$rutas_nuevas" "$pruebas"; do
  mkdir -p "$(dirname "$nuevo")"
  cp "$origen/$nuevo" "$nuevo"
  chmod 644 "$nuevo"
  instalados+=("$nuevo")
done
cp "$origen/$vista" "$vista"
chmod 644 "$vista"
# No tocar routes/web.php ni los archivos corporativos.
printf '\n// Edición y eliminación protegidas de conexiones WhatsApp.\nrequire __DIR__."/administracion_whatsapp_edicion.php";\n' >> "$rutas"
php -l "$rutas"
php artisan route:list --path=administracion/whatsapp | grep -F 'whatsapp.edicion.actualizar'
php artisan route:list --path=administracion/whatsapp | grep -F 'whatsapp.edicion.eliminar'

if [ -x vendor/bin/phpunit ]; then
  vendor/bin/phpunit "$pruebas"
else
  echo 'AVISO=PHPUNIT_NO_DISPONIBLE'
fi

echo 'EDICION_CONEXIONES=INSTALADA'
echo 'LOGIN_CORPORATIVO=NO_MODIFICADO'
echo 'GESTOR_WHATSAPP=NO_REINICIADO'
echo 'SESIONES_Y_BASE_DATOS=NO_MODIFICADAS_POR_INSTALACION'
echo "RESPALDO=$respaldo"