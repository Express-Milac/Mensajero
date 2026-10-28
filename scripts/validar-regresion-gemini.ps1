# Regresion completa del circuito Gemini en SQL Server de integracion.
# Uso: powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\validar-regresion-gemini.ps1
# NO ejecuta workers, no llama Google, no prepara despliegue.
$ErrorActionPreference = "Stop"

$raiz = Split-Path -Parent $PSScriptRoot
Set-Location $raiz

$ramaEsperada = "integracion/normalizacion-gemini-validada-20261028"
if ((git branch --show-current).Trim() -ne $ramaEsperada) {
    throw "Rama incorrecta: no ejecutar la regresion."
}
if (Test-Path ".\bootstrap\cache\config.php") {
    throw "Configuracion Laravel en cache: no ejecutar la regresion."
}
if (-not (Test-Path ".\phpunit.integracion.xml")) {
    throw "Falta phpunit.integracion.xml."
}
if (-not (Test-Path ".\vendor\bin\phpunit.bat")) {
    throw "Falta PHPUnit local."
}

git diff --quiet
if ($LASTEXITCODE -ne 0) {
    throw "Hay cambios locales versionados: no continuar."
}
git diff --cached --quiet
if ($LASTEXITCODE -ne 0) {
    throw "Hay cambios preparados para commit: no continuar."
}

$nombres = @(
    "APP_ENV", "DB_CONNECTION", "DB_DATABASE", "DB_URL",
    "IA_HABILITADA", "MENSAJERO_IA_ENVIO_AUTORIZADO",
    "MENSAJERO_IA_SERVICIO_PAGADO_CONFIRMADO",
    "MENSAJERO_IA_LOTES_HABILITADOS",
    "MENSAJERO_IA_CONCILIACION_AUTOMATICA",
    "MENSAJERO_IA_EJECUCION_AUTOMATICA",
    "MENSAJERO_NORMALIZACION_HABILITADA",
    "MENSAJERO_TIEMPO_REAL_HABILITADO",
    "QUEUE_CONNECTION"
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
    $env:MENSAJERO_IA_CONCILIACION_AUTOMATICA = "false"
    $env:MENSAJERO_IA_EJECUCION_AUTOMATICA = "false"
    $env:MENSAJERO_NORMALIZACION_HABILITADA = "false"
    $env:MENSAJERO_TIEMPO_REAL_HABILITADO = "false"
    $env:QUEUE_CONNECTION = "sync"

    Write-Host "`n=== REGRESION SQL SERVER COMPLETA ==="
    Write-Host "Base objetivo: mensajero_integracion_test"
    Write-Host "Gemini, conciliacion, normalizacion automatica y tiempo real: apagados"
    Write-Host "Sin trabajadores de colas"

    .\vendor\bin\phpunit.bat -c phpunit.integracion.xml

    if ($LASTEXITCODE -ne 0) {
        throw "Regresion completa NO aprobada. Revisar salida antes de repetir."
    }

    Write-Host "`n=== REGRESION SQL SERVER APROBADA ==="
}
finally {
    foreach ($nombre in $nombres) {
        [Environment]::SetEnvironmentVariable(
            $nombre, $anteriores[$nombre], "Process"
        )
    }
}
