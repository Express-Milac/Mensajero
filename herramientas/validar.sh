#!/usr/bin/env bash
set -euo pipefail

INTEGRACION="${1:-}"

command -v php >/dev/null || { echo "PHP no esta disponible." >&2; exit 1; }
command -v composer >/dev/null || { echo "Composer no esta disponible." >&2; exit 1; }

php -r 'if (version_compare(PHP_VERSION, "8.4.0", "<")) { fwrite(STDERR, "Se requiere PHP 8.4 o superior.\n"); exit(1); }'

composer install --no-interaction --prefer-dist --no-progress
php artisan test
php vendor/laravel/pint/builds/pint --test
php artisan view:cache
php artisan route:list --except-vendor
composer validate --strict

if [[ "$INTEGRACION" == "--integracion" ]]; then
    php -r 'foreach (["sqlsrv", "pdo_sqlsrv"] as $ext) { if (!extension_loaded($ext)) { fwrite(STDERR, "Falta extension: $ext\n"); exit(1); } }'
    php artisan test --configuration=phpunit.integration.xml
else
    echo "Validacion de codigo aprobada. Para SQL Server use: herramientas/validar.sh --integracion"
fi
