# Ensayo aislado de una sola ejecucion de cola Redis con PHP 7.4.13.
# No se conecta a SQL Server ni modifica colas operativas.
$ErrorActionPreference = "Stop"

$proyecto = Split-Path -Parent $PSScriptRoot
$base = Split-Path -Parent $proyecto
$php74 = Join-Path $base "Herramientas-Mensajero-PHP74\PHP-7.4.13\php.exe"
$herramienta = Join-Path $PSScriptRoot "ensayar-cola-redis-php74.php"
$trabajo = Join-Path $proyecto "tests\Soporte\TrabajoColaRedisPhp74.php"

if (-not (Test-Path -LiteralPath $php74 -PathType Leaf) -or
    -not (Test-Path -LiteralPath $herramienta -PathType Leaf) -or
    -not (Test-Path -LiteralPath $trabajo -PathType Leaf) -or
    -not (Test-Path -LiteralPath (Join-Path $proyecto "vendor\autoload.php") -PathType Leaf)) {
    throw "Faltan PHP 7.4, autoload o archivos del ensayo."
}
if (Test-Path -LiteralPath (Join-Path $proyecto "bootstrap\cache\config.php")) {
    throw "Laravel tiene configuracion en cache. No se ejecuta la prueba."
}

$id = [guid]::NewGuid().ToString("N")
$cola = "mensajero_ensayo_php74_$id"
$prefijo = "mensajero:prueba_php74:$($id):"

$valores = @{
    APP_ENV = "testing"
    APP_DEBUG = "false"
    APP_KEY = "base64:MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY="
    DB_CONNECTION = "sqlite"
    DB_DATABASE = ":memory:"
    DB_URL = ""
    QUEUE_CONNECTION = "redis"
    QUEUE_FAILED_DRIVER = "null"
    REDIS_CLIENT = "predis"
    REDIS_HOST = "127.0.0.1"
    REDIS_PORT = "6379"
    REDIS_PASSWORD = ""
    REDIS_DB = "0"
    REDIS_QUEUE_CONNECTION = "default"
    REDIS_QUEUE = $cola
    REDIS_PREFIX = $prefijo
    CACHE_STORE = "array"
    CACHE_DRIVER = "array"
    SESSION_DRIVER = "array"
    LOG_CHANNEL = "stderr"
    MENSAJERO_REDIS74_ID = $id
    MENSAJERO_REDIS74_ENSAYO = "autorizado_local"
    IA_HABILITADA = "false"
    MENSAJERO_IA_ENVIO_AUTORIZADO = "false"
    MENSAJERO_IA_LOTES_HABILITADOS = "false"
    MENSAJERO_NORMALIZACION_HABILITADA = "false"
    MENSAJERO_GEMINI_HABILITADO = "false"
}
$anteriores = @{}
foreach ($nombre in $valores.Keys) {
    $anteriores[$nombre] = [Environment]::GetEnvironmentVariable($nombre, "Process")
}

Push-Location $proyecto
try {
    & $php74 -l $herramienta
    if ($LASTEXITCODE -ne 0) { throw "Error de sintaxis de la herramienta." }
    & $php74 -l $trabajo
    if ($LASTEXITCODE -ne 0) { throw "Error de sintaxis del trabajo." }

    foreach ($nombre in $valores.Keys) {
        [Environment]::SetEnvironmentVariable($nombre, $valores[$nombre], "Process")
    }

    Write-Host ""
    Write-Host "=== ENSAYO REDIS PHP74 CON COLA EXCLUSIVA ==="
    Write-Host "Cola unica: $cola"
    Write-Host "Solo SQL en memoria; no se usan colas ni claves existentes."

    & $php74 $herramienta preparar
    if ($LASTEXITCODE -ne 0) {
        throw "Preparacion de la cola aislada fallida. No iniciar trabajador."
    }

    Write-Host ""
    Write-Host "=== TRABAJADOR DE LARAVEL: UN SOLO TRABAJO ==="
    & $php74 ".\artisan" queue:work redis "--queue=$cola" --once --tries=1 --timeout=15 --sleep=0 --no-interaction
    if ($LASTEXITCODE -ne 0) {
        throw "El trabajador Redis termino con error."
    }

    Write-Host ""
    Write-Host "=== VERIFICAR ACUSE Y LIMPIAR SOLO CLAVES DE PRUEBA ==="
    & $php74 $herramienta verificar
    if ($LASTEXITCODE -ne 0) {
        throw "No se comprobo el acuse del trabajo de prueba."
    }

    Write-Host "=== TRABAJADOR REDIS PHP 7.4 APROBADO ==="
} finally {
    foreach ($nombre in $valores.Keys) {
        [Environment]::SetEnvironmentVariable($nombre, $anteriores[$nombre], "Process")
    }
    $valores = $null
    Pop-Location
    Write-Host "Variables temporales restauradas. No se utilizo SQL Server."
}
