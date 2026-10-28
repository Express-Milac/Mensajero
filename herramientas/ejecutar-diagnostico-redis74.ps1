# Verificacion local y estrictamente de lectura de Redis + Laravel 8 con PHP 7.4.13.
# No usa SQL Server, no publica trabajos, no cambia configuraciones persistentes.
$ErrorActionPreference = "Stop"

$proyecto = Split-Path -Parent $PSScriptRoot
$carpetaBase = Split-Path -Parent $proyecto
$php74 = Join-Path $carpetaBase "Herramientas-Mensajero-PHP74\PHP-7.4.13\php.exe"
$diagnostico = Join-Path $PSScriptRoot "diagnosticar-redis-php74.php"

if (-not (Test-Path -LiteralPath $php74 -PathType Leaf)) {
    throw "No se encontro PHP 7.4.13 local."
}
if (-not (Test-Path -LiteralPath $diagnostico -PathType Leaf)) {
    throw "Falta la herramienta de diagnostico Redis."
}
if (Test-Path -LiteralPath (Join-Path $proyecto "bootstrap\cache\config.php")) {
    throw "Laravel tiene configuracion en cache: no se evaluara Redis."
}

$valores = @{
    APP_ENV = "testing"
    REDIS_CLIENT = "predis"
    REDIS_HOST = "127.0.0.1"
    REDIS_PORT = "6379"
    REDIS_PASSWORD = ""
    REDIS_DB = "0"
    REDIS_CACHE_DB = "1"
    REDIS_PREFIX = "mensajero:diagnostico_php74:"
    DB_CONNECTION = "sqlite"
    DB_DATABASE = ":memory:"
    DB_URL = ""
    QUEUE_CONNECTION = "sync"
    CACHE_STORE = "array"
    CACHE_DRIVER = "array"
    SESSION_DRIVER = "array"
    IA_HABILITADA = "false"
    MENSAJERO_IA_ENVIO_AUTORIZADO = "false"
    MENSAJERO_IA_LOTES_HABILITADOS = "false"
    MENSAJERO_NORMALIZACION_HABILITADA = "false"
}

$anteriores = @{}
foreach ($nombre in $valores.Keys) {
    $anteriores[$nombre] = [Environment]::GetEnvironmentVariable($nombre, "Process")
}

Push-Location $proyecto
try {
    foreach ($nombre in $valores.Keys) {
        [Environment]::SetEnvironmentVariable($nombre, $valores[$nombre], "Process")
    }

    Write-Host "=== PHP 7.4 / LARAVEL 8 / PREDIS / REDIS LOCAL ==="
    Write-Host "Prueba: PING sin escribir claves ni ejecutar trabajadores."
    Write-Host "SQL Server y WhatsApp no se utilizan."

    & $php74 $diagnostico
    if ($LASTEXITCODE -ne 0) {
        throw "El diagnostico Redis no aprobo; no ejecutar aun un trabajador."
    }
    Write-Host "=== REDIS LOCAL PHP 7.4: CONEXION APROBADA ==="
} finally {
    foreach ($nombre in $valores.Keys) {
        [Environment]::SetEnvironmentVariable($nombre, $anteriores[$nombre], "Process")
    }
    $valores = $null
    Pop-Location
    Write-Host "Variables del proceso restauradas."
}
