#!/usr/bin/env bash
# Resolver dependencias solo de Mensajero en una carpeta temporal.
# No usa SQL Server, Redis, WhatsApp, Gemini ni toca el composer.lock del repositorio.
set -euo pipefail

raiz="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
composer_bin="${COMPOSER_BIN:-composer}"

# Composer 2 puede estar instalado como ejecutable O como archivo PHAR privado.
if [[ -n "${COMPOSER_PHAR:-}" ]]; then
    if [[ ! -f "$COMPOSER_PHAR" ]]; then
        echo "ERROR: no existe COMPOSER_PHAR." >&2
        exit 2
    fi
    ejecutar_composer=(php "$COMPOSER_PHAR")
else
    if ! command -v "$composer_bin" >/dev/null 2>&1; then
        echo "ERROR: Composer 2 debe estar disponible en el equipo de desarrollo." >&2
        exit 2
    fi
    ejecutar_composer=("$composer_bin")
fi
version_composer="$("${ejecutar_composer[@]}" --version --no-ansi 2>&1)"
if [[ ! "$version_composer" =~ Composer[[:space:]]version[[:space:]]2\. ]]; then
    echo "ERROR: se necesita Composer 2 separado de Composer 1 del servidor." >&2
    exit 2
fi

# El archivo del proyecto fija el PHP objetivo para la resolución.
if ! php -r '
$j = json_decode(file_get_contents($argv[1]), true);
exit((isset($j["config"]["platform"]["php"]) &&
       $j["config"]["platform"]["php"] === "7.4.13" &&
       isset($j["require"]["laravel/framework"]) &&
       $j["require"]["laravel/framework"] === "^8.83") ? 0 : 1);
' "$raiz/composer.json"; then
    echo "ERROR: composer.json ya no apunta a PHP 7.4.13 / Laravel 8.83." >&2
    exit 2
fi

directorio="$(mktemp -d "${TMPDIR:-/tmp}/mensajero-lock-php74.XXXXXXXX")"
echo "Copia aislada: $directorio"
cp "$raiz/composer.json" "$directorio/composer.json"
mkdir -p "$directorio/herramientas"
cp "$raiz/herramientas/verificar-lock-php74.php" "$directorio/herramientas/verificar-lock-php74.php"

# La carpeta temporal no contiene .env, contraseñas, datos SQL ni historial WhatsApp.
(
    cd "$directorio"
    "${ejecutar_composer[@]}" update --no-install --no-scripts --no-plugins --no-interaction --prefer-dist --no-progress
    php herramientas/verificar-lock-php74.php
)

echo
echo "Resultado temporal: $directorio/composer.lock"
echo "REVISAR cambios y requisitos; NO se modificó composer.lock en Mensajero."
echo "NO ejecutar este script en el servidor de MILAC."
