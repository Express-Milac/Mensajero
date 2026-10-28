# Pruebas SQL Server reales con PHP 7.4: SOLO Docker local y BASE NUEVA.
# Este guion CREA UNA BASE de prueba nueva. NO toca mensajero_test.
# No ejecuta ninguna prueba si la base nueva no se verifica vacia.
$ErrorActionPreference = "Stop"

$base = "C:\Users\6MGHN64_\Documents\Desarrollo Milac"
$proyecto = Join-Path $base "Mensajero-PHP74-Pruebas"
$origen = Join-Path $base "Mensajero-Integracion-Gemini\.env"
$php74 = Join-Path $base "Herramientas-Mensajero-PHP74\PHP-7.4.13\php.exe"
$creador = Join-Path $proyecto "herramientas\crear-base-sql-php74-desechable.php"

if (-not (Test-Path -LiteralPath $origen -PathType Leaf) -or
    -not (Test-Path -LiteralPath $php74 -PathType Leaf) -or
    -not (Test-Path -LiteralPath $creador -PathType Leaf)) {
    throw "Falta .env de integración, PHP7.4 o herramienta de creacion."
}
if (Test-Path -LiteralPath (Join-Path $proyecto "bootstrap\cache\config.php")) {
    throw "Configuracion de Laravel en cache: no continuar."
}

$requeridos = @("DB_CONNECTION", "DB_HOST", "DB_PORT", "DB_DATABASE", "DB_USERNAME", "DB_PASSWORD")
$datos = @{}
foreach ($linea in Get-Content -LiteralPath $origen) {
    if ($linea -match '^\s*(DB_CONNECTION|DB_HOST|DB_PORT|DB_DATABASE|DB_USERNAME|DB_PASSWORD|DB_URL)\s*=\s*(.*)$') {
        $nombre = $Matches[1]
        $valor = $Matches[2].Trim()
        if ($valor.Length -ge 2) {
            if (($valor[0] -eq '"' -and $valor[$valor.Length-1] -eq '"') -or
                ($valor[0] -eq "'" -and $valor[$valor.Length-1] -eq "'")) {
                $valor = $valor.Substring(1, $valor.Length-2)
            }
        }
        $datos[$nombre] = $valor
    }
}

if ($datos["DB_CONNECTION"] -ne "sqlsrv" -or
    $datos["DB_DATABASE"] -ne "mensajero_test" -or
    $datos["DB_HOST"] -notin @("127.0.0.1", "localhost") -or
    $datos["DB_URL"]) {
    throw "El .env origen no esta restringido al SQL local mensajero_test."
}
foreach ($nombre in $requeridos) {
    if ([string]::IsNullOrWhiteSpace($datos[$nombre])) {
        throw "Falta campo de origen requerido: $nombre"
    }
}

# Confirmar que el puerto local realmente pertenece al contenedor de pruebas.
if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    throw "Docker no esta disponible."
}
$estado = @(docker inspect --format '{{.State.Running}}' mensajero-sqlserver 2>$null)
if ($LASTEXITCODE -ne 0 -or $estado.Count -ne 1 -or $estado[0] -ne "true") {
    throw "El contenedor mensajero-sqlserver no esta activo."
}
$puertos = @(docker port mensajero-sqlserver 1433/tcp 2>$null)
if ($LASTEXITCODE -ne 0 -or $puertos.Count -eq 0) {
    throw "No hay puerto SQL publicado por Docker."
}
$coincide = $false
foreach ($lineaPuerto in $puertos) {
    if ($lineaPuerto -match ':(\d+)$' -and $Matches[1] -eq $datos["DB_PORT"]) {
        $coincide = $true
    }
}
if (-not $coincide) {
    throw "DB_PORT no corresponde al puerto publicado por mensajero-sqlserver."
}

# Variables de proceso, sin guardar ni imprimir la contrasena.
$valores = @{
    APP_ENV = "testing"
    DB_CONNECTION = "sqlsrv"
    DB_HOST = $datos["DB_HOST"]
    DB_PORT = $datos["DB_PORT"]
    DB_DATABASE = "mensajero_test"
    DB_USERNAME = $datos["DB_USERNAME"]
    DB_PASSWORD = $datos["DB_PASSWORD"]
    DB_URL = ""
    DB_ENCRYPT = "yes"
    DB_TRUST_SERVER_CERTIFICATE = "true"
    MENSAJERO_SQL_DOCKER_VERIFICADO = "mensajero-sqlserver"
    QUEUE_CONNECTION = "sync"
    CACHE_STORE = "array"
    CACHE_DRIVER = "array"
    SESSION_DRIVER = "array"
    IA_HABILITADA = "false"
    MENSAJERO_IA_ENVIO_AUTORIZADO = "false"
    MENSAJERO_IA_LOTES_HABILITADOS = "false"
    MENSAJERO_IA_SERVICIO_PAGADO_CONFIRMADO = "false"
    MENSAJERO_IA_EJECUCION_AUTOMATICA = "false"
    MENSAJERO_IA_CONCILIACION_AUTOMATICA = "false"
    MENSAJERO_NORMALIZACION_HABILITADA = "false"
    MENSAJERO_TIEMPO_REAL_HABILITADO = "false"
    MENSAJERO_GEMINI_HABILITADO = "false"
}
$anteriores = @{}
foreach ($nombre in $valores.Keys) {
    $anteriores[$nombre] = [Environment]::GetEnvironmentVariable($nombre, "Process")
}

Set-Location $proyecto
try {
    foreach ($nombre in $valores.Keys) {
        [Environment]::SetEnvironmentVariable($nombre, $valores[$nombre], "Process")
    }
    Write-Host "=== CREAR BASE NUEVA SOLO EN DOCKER, SIN TOCAR mensajero_test ==="
    $salida = @(& $php74 $creador)
    if ($LASTEXITCODE -ne 0) {
        throw "No se pudo preparar la base de pruebas aislada."
    }
    $registro = @($salida | Where-Object { $_ -match '^BASE_DESECHABLE_CREADA: mensajero_php74_[a-f0-9]{10}_test$' })
    if ($registro.Count -ne 1 -or $salida -notcontains "BASE_VERIFICADA_VACIA: SI") {
        throw "No se verifico la base nueva vacia; no se ejecutaran migraciones."
    }
    $nueva = ($registro[0] -split ': ', 2)[1]
    Write-Host "Nueva base exclusiva de PHP 7.4: $nueva"
    Write-Host "mensajero_test queda sin modificar."
    $env:DB_DATABASE = $nueva

    Write-Host "`n=== PRIMERA MIGRACION Y ESQUEMA SQL SERVER ==="
    & $php74 ".\vendor\bin\phpunit" -c phpunit.integracion.xml --filter EsquemaCapturaSqlServerTest
    if ($LASTEXITCODE -ne 0) {
        throw "Falló la prueba de esquema. No lanzar suite completa."
    }

    Write-Host "`n=== SUITE DE INTEGRACION SQL SERVER PHP 7.4 ==="
    & $php74 ".\vendor\bin\phpunit" -c phpunit.integracion.xml --stop-on-failure
    if ($LASTEXITCODE -ne 0) {
        throw "Se detectó fallo en pruebas SQL. Revisar salida y corregir código."
    }

    Write-Host "`n=== INTEGRACION SQL SERVER PHP 7.4 APROBADA ==="
    Write-Host "La base de prueba creada permanece disponible para auditarla: $nueva"
} finally {
    foreach ($nombre in $valores.Keys) {
        [Environment]::SetEnvironmentVariable($nombre, $anteriores[$nombre], "Process")
    }
    $datos = $null
    $valores = $null
    Write-Host "Variables de proceso restauradas. Ninguna base fue eliminada."
}
