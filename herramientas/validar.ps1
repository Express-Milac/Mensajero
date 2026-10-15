param(
    [switch]$Integracion
)

$ErrorActionPreference = 'Stop'

if (-not (Get-Command php -ErrorAction SilentlyContinue)) { throw 'PHP no esta disponible.' }
if (-not (Get-Command composer -ErrorAction SilentlyContinue)) { throw 'Composer no esta disponible.' }

php -r 'if (version_compare(PHP_VERSION, "8.4.0", "<")) { fwrite(STDERR, "Se requiere PHP 8.4 o superior.\n"); exit(1); }'
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

composer install --no-interaction --prefer-dist --no-progress
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

php artisan test
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

php vendor/laravel/pint/builds/pint --test
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

php artisan view:cache
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

php artisan route:list --except-vendor
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

composer validate --strict
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

if ($Integracion) {
    php -r 'foreach (["sqlsrv", "pdo_sqlsrv"] as $ext) { if (!extension_loaded($ext)) { fwrite(STDERR, "Falta extension: $ext\n"); exit(1); } }'
    if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

    php artisan test --configuration=phpunit.integration.xml
    if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
} else {
    Write-Host 'Validacion de codigo aprobada. Para SQL Server use: .\herramientas\validar.ps1 -Integracion'
}
