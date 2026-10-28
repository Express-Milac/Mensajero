# Migración y pruebas del LIBRO de presupuesto SOLO en mensajero_integracion_test.
# No habilita Gemini, no utiliza Redis ni contacta Google.
$ErrorActionPreference = "Stop"
Set-Location (Split-Path -Parent $PSScriptRoot)

$rama = "integracion/normalizacion-gemini-validada-20261028"
if ((git branch --show-current).Trim() -ne $rama) {
    throw "Rama incorrecta."
}
if (Test-Path ".\bootstrap\cache\config.php") {
    throw "Existe configuracion Laravel en cache."
}
git diff --quiet
if ($LASTEXITCODE -ne 0) { throw "Hay cambios locales." }
git diff --cached --quiet
if ($LASTEXITCODE -ne 0) { throw "Hay cambios preparados en Git." }

$nombres = @(
    "APP_ENV", "DB_CONNECTION", "DB_DATABASE", "DB_URL",
    "IA_HABILITADA", "MENSAJERO_IA_ENVIO_AUTORIZADO",
    "MENSAJERO_IA_SERVICIO_PAGADO_CONFIRMADO",
    "MENSAJERO_IA_LOTES_HABILITADOS", "MENSAJERO_IA_PRESUPUESTO_HABILITADO",
    "MENSAJERO_IA_TARIFA_VALIDADA", "MENSAJERO_IA_EJECUCION_AUTOMATICA",
    "MENSAJERO_IA_CONCILIACION_AUTOMATICA", "MENSAJERO_NORMALIZACION_HABILITADA",
    "MENSAJERO_TIEMPO_REAL_HABILITADO", "QUEUE_CONNECTION"
)
$anteriores = @{}
foreach ($nombre in $nombres) {
    $anteriores[$nombre] = [Environment]::GetEnvironmentVariable($nombre, "Process")
}

try {
    $env:APP_ENV = "testing"
    $env:DB_CONNECTION = "sqlsrv"
    $env:DB_DATABASE = "mensajero_integracion_test"
    $env:DB_URL = ""
    $env:IA_HABILITADA = "false"
    $env:MENSAJERO_IA_ENVIO_AUTORIZADO = "false"
    $env:MENSAJERO_IA_SERVICIO_PAGADO_CONFIRMADO = "false"
    $env:MENSAJERO_IA_LOTES_HABILITADOS = "false"
    $env:MENSAJERO_IA_PRESUPUESTO_HABILITADO = "false"
    $env:MENSAJERO_IA_TARIFA_VALIDADA = "false"
    $env:MENSAJERO_IA_EJECUCION_AUTOMATICA = "false"
    $env:MENSAJERO_IA_CONCILIACION_AUTOMATICA = "false"
    $env:MENSAJERO_NORMALIZACION_HABILITADA = "false"
    $env:MENSAJERO_TIEMPO_REAL_HABILITADO = "false"
    $env:QUEUE_CONNECTION = "sync"

    $archivos = @(
        "scripts/verificar-base-presupuesto-gemini.php",
        "database/migrations/2026_10_28_000400_crear_reservas_presupuesto_gemini.php",
        "app/Services/IA/ReservarPresupuestoGemini.php",
        "tests/Integracion/ReservarPresupuestoGeminiSqlServerTest.php"
    )
    foreach ($archivo in $archivos) {
        php -l $archivo
        if ($LASTEXITCODE -ne 0) { throw "Sintaxis invalida: $archivo" }
    }

    Write-Host "=== DESTINO SQL FISICO ==="
    php scripts/verificar-base-presupuesto-gemini.php
    if ($LASTEXITCODE -ne 0) { throw "Bloqueada: base SQL no autorizada." }

    Write-Host "=== MIGRACION NUEVA SOLO EN BASE TEST ==="
    php artisan migrate --database=sqlsrv --path=database/migrations/2026_10_28_000400_crear_reservas_presupuesto_gemini.php --force --no-interaction
    if ($LASTEXITCODE -ne 0) { throw "Fallo la migracion de pruebas." }

    Write-Host "=== 6 PRUEBAS DE RESERVA MONETARIA ==="
    .\vendor\bin\phpunit.bat -c phpunit.integracion.xml tests/Integracion/ReservarPresupuestoGeminiSqlServerTest.php
    if ($LASTEXITCODE -ne 0) { throw "Libro de presupuesto: pruebas no aprobadas." }

    Write-Host "=== REGRESION COMPLETA SQL SERVER ==="
    .\vendor\bin\phpunit.bat -c phpunit.integracion.xml
    if ($LASTEXITCODE -ne 0) { throw "Regresion completa no aprobada." }

    Write-Host "=== PRESUPUESTO FASE 1 Y REGRESION SQL APROBADOS ==="
} finally {
    foreach ($nombre in $nombres) {
        [Environment]::SetEnvironmentVariable($nombre, $anteriores[$nombre], "Process")
    }
}
