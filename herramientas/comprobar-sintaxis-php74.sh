#!/usr/bin/env bash
# Verifica sintaxis con el intérprete PHP del entorno, sin arrancar Laravel,
# instalar Composer, acceder a SQL Server ni tocar archivos de Mensajero.
set -u

cd "$(dirname "$0")/.." || exit 2

version="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null)"
if [ "$version" != "7.4" ]; then
    printf 'ERROR: Esta comprobación exige PHP 7.4 real, encontrado %s\n' "$version" >&2
    exit 2
fi

errores=0
revisados=0

while IFS= read -r archivo; do
    revisados=$((revisados + 1))
    if ! php -l "$archivo" >/dev/null 2>&1; then
        printf '\nINCOMPATIBLE: %s\n' "$archivo"
        php -l "$archivo" 2>&1 || true
        errores=$((errores + 1))
    fi
done < <(find app bootstrap config database routes public -type f -name '*.php' | sort)

printf '\nRevisados: %s | Incompatibles PHP 7.4: %s\n' "$revisados" "$errores"
if [ "$errores" -ne 0 ]; then
    printf 'La rama NO es instalable en PHP 7.4 todavía.\n' >&2
    exit 1
fi

printf 'Sintaxis aceptada por PHP 7.4 (aún faltan pruebas de API y runtime).\n'
