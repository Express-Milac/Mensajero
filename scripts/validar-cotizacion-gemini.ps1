# Prueba aritmética del cotizador y regresión SQL existente.
# El cotizador NO reserva dinero, NO modifica IA y NO contacta Google.
$ErrorActionPreference = "Stop"

Set-Location (Split-Path -Parent $PSScriptRoot)
$rama = "integracion/normalizacion-gemini-validada-20261028"

if ((git branch --show-current).Trim() -ne $rama) {
    throw "Rama incorrecta."
}
if (Test-Path ".\bootstrap\cache\config.php") {
    throw "Configuracion Laravel en cache."
}
git diff --quiet
if ($LASTEXITCODE -ne 0) { throw "Cambios locales presentes." }
git diff --cached --quiet
if ($LASTEXITCODE -ne 0) { throw "Cambios preparados presentes." }

$nombres = @(
    "APP_ENV", "DB_CONNECTION", "DB_DATABASE", "DB_URL",
    "IA_HABILITADA", "MENSAJERO_IA_ENVIO_AUTORIZADO",
    "MENSAJERO_IA_SERVICIO_PAGADO_CONFIRMADO",
    "MENSAJERO_IA_LOTES_HABILITADOS",
    "MENSAJERO_IA_PRESUPUESTO_HABILITADO", "MENSAJERO_IA_TARIFA_VALIDADA",
    "MENSAJERO_IA_EJECUCION_AUTOMATICA", "MENSAJERO_IA_CONCILIACION_AUTOMATICA",
    "MENSAJERO_NORMALIZACION_HABILITADA",
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
        "app/Services/IA/CotizarCostoReferenciaGemini.php",
        "tests/Unidad/CotizarCostoReferenciaGeminiTest.php"
    )

    foreach ($archivo in $archivos) {
        php -l $archivo
        if ($LASTEXITCODE -ne 0) { throw "Fallo de sintaxis: $archivo" }
    }

    Write-Host "=== 4 PRUEBAS DE COTIZACION, SIN RED ==="
    .\vendor\bin\phpunit.bat -c phpunit.xml tests/Unidad/CotizarCostoReferenciaGeminiTest.php
    if ($LASTEXITCODE -ne 0) {
        throw "Fallo del cotizador de referencia."
    }

    Write-Host "=== PROTECCION DE BASE FISICA SQL SERVER ==="
    php scripts/verificar-base-presupuesto-gemini.php
    if ($LASTEXITCODE -ne 0) {
        throw "No se verifico la base SQL test; regresion bloqueada."
    }

    Write-Host "=== REGRESION INTEGRAL SQL SERVER ==="
    .\vendor\bin\phpunit.bat -c phpunit.integracion.xml
    if ($LASTEXITCODE -ne 0) {
        throw "La regresion SQL Server fallo."
    }

    Write-Host "=== COTIZADOR Y REGRESION SQL APROBADOS ==="
}
finally {
    foreach ($nombre in $nombres) {
        [Environment]::SetEnvironmentVariable($nombre, $anteriores[$nombre], "Process")
    }
}
