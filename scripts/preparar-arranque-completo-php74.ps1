# Prepara SOLO el clon local PHP74 con configuracion de su instalacion local existente.
# No inicia servicios, no modifica la configuracion origen, no expone secretos.
# No sustituye autorizaciones de uso de WhatsApp/Gemini ni certificados.
[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$destino = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$base = Split-Path -Parent $destino
$origen = Join-Path $base 'Mensajero'
$php74 = Join-Path $base 'Herramientas-Mensajero-PHP74\PHP-7.4.13\php.exe'
$origenRecolector = Join-Path $origen 'recolector-whatsapp'
$destinoRecolector = Join-Path $destino 'recolector-whatsapp'
$origenEnv = Join-Path $origen '.env'
$origenRecolectorEnv = Join-Path $origenRecolector '.env'
$destinoEnv = Join-Path $destino '.env'
$destinoRecolectorEnv = Join-Path $destinoRecolector '.env'

function Exigir([bool] $condicion, [string] $mensaje) {
    if (-not $condicion) { throw $mensaje }
}

function LeerValor([string] $contenido, [string] $nombre) {
    $patron = '(?m)^\s*' + [regex]::Escape($nombre) + '\s*=\s*(.*)$'
    $coincidencia = [regex]::Match($contenido, $patron)
    if (-not $coincidencia.Success) { return '' }
    return $coincidencia.Groups[1].Value.Trim().Trim('"').Trim("'")
}

function EstablecerValor([string] $contenido, [string] $nombre, [string] $valor) {
    $patron = '(?m)^\s*' + [regex]::Escape($nombre) + '\s*=.*$'
    $linea = $nombre + '=' + $valor
    if ([regex]::IsMatch($contenido, $patron)) {
        return [regex]::Replace(
            $contenido, $patron,
            [System.Text.RegularExpressions.MatchEvaluator]{ param($m) return $linea }
        )
    }
    return $contenido.TrimEnd() + [Environment]::NewLine + $linea + [Environment]::NewLine
}

function RutaAbsoluta([string] $raiz, [string] $ruta) {
    if ([string]::IsNullOrWhiteSpace($ruta)) { return '' }
    if ([IO.Path]::IsPathRooted($ruta)) { return [IO.Path]::GetFullPath($ruta) }
    return [IO.Path]::GetFullPath((Join-Path $raiz $ruta))
}

Write-Host '=== PREPARAR ARRANQUE COMPLETO MENSAJERO / PHP 7.4 ===' -ForegroundColor Cyan
Exigir (Test-Path -LiteralPath $php74 -PathType Leaf) 'No existe el PHP privado 7.4.13.'
Exigir (Test-Path -LiteralPath $origenEnv -PathType Leaf) 'No existe el .env del Mensajero local original. No se inventaran claves.'
Exigir (Test-Path -LiteralPath $origenRecolectorEnv -PathType Leaf) 'No existe el .env del recolector local original.'
Exigir (Test-Path -LiteralPath (Join-Path $destino 'vendor\autoload.php') -PathType Leaf) 'Falta vendor del clon PHP74.'
Exigir (Test-Path -LiteralPath (Join-Path $destinoRecolector 'node_modules') -PathType Container) 'Faltan node_modules del recolector PHP74.'
Exigir (-not (Test-Path -LiteralPath $destinoEnv -PathType Leaf)) 'El clon ya tiene .env; NO se sobrescribira. Revisar manualmente.'
Exigir (-not (Test-Path -LiteralPath $destinoRecolectorEnv -PathType Leaf)) 'El recolector ya tiene .env; NO se sobrescribira.'

$raizContenido = [IO.File]::ReadAllText($origenEnv)
$recolectorContenido = [IO.File]::ReadAllText($origenRecolectorEnv)
$dbHost = LeerValor $raizContenido 'DB_HOST'
$dbName = LeerValor $raizContenido 'DB_DATABASE'
$dbUrl = LeerValor $raizContenido 'DB_URL'
$dbPort = LeerValor $raizContenido 'DB_PORT'
Exigir ($dbHost -in @('127.0.0.1','localhost') -and $dbName -eq 'mensajero_test' -and [string]::IsNullOrWhiteSpace($dbUrl)) 'La configuracion origen no usa exclusivamente SQL Server local mensajero_test. No copiar.'
Exigir ((LeerValor $raizContenido 'DB_CONNECTION') -eq 'sqlsrv') 'El origen no usa DB_CONNECTION=sqlsrv.'
Exigir ($dbPort -eq '1433') 'DB_PORT no coincide con 1433 de Docker local.'
Exigir (-not [string]::IsNullOrWhiteSpace((LeerValor $raizContenido 'APP_KEY'))) 'Falta APP_KEY en origen.'
Exigir ((LeerValor $raizContenido 'WHATSAPP_PASARELA_SECRETO') -eq (LeerValor $recolectorContenido 'MENSAJERO_PASARELA_SECRETO')) 'Los secretos Laravel/recolector difieren (valores no mostrados).'
Exigir ((LeerValor $raizContenido 'WHATSAPP_PASARELA_SECRETO').Length -ge 32) 'Secreto de pasarela demasiado corto.'
Exigir ((LeerValor $recolectorContenido 'MENSAJERO_CLAVE_CONEXION').Length -gt 0) 'Falta clave de conexion en recolector.'
$docker = Get-Command docker -ErrorAction SilentlyContinue
Exigir ($null -ne $docker) 'No se encontro Docker. El SQL local requiere el contenedor mensajero-sqlserver.'
$estadoDocker = @(& docker inspect --format '{{.State.Running}}' mensajero-sqlserver 2>$null)
Exigir ($LASTEXITCODE -eq 0 -and $estadoDocker.Count -eq 1 -and $estadoDocker[0] -eq 'true') 'El contenedor mensajero-sqlserver no esta iniciado.'
$puertoDocker = @(& docker port mensajero-sqlserver 1433/tcp 2>$null)
Exigir ($LASTEXITCODE -eq 0 -and @($puertoDocker | Where-Object { $_ -match ':1433$' }).Count -gt 0) 'Docker no publica SQL Server en el puerto 1433.'
$datos = LeerValor $recolectorContenido 'RECOLECTOR_RUTA_DATOS'
if ([string]::IsNullOrWhiteSpace($datos)) { $datos = './datos' }
$rutaDatos = RutaAbsoluta $origenRecolector $datos
Exigir (Test-Path -LiteralPath (Join-Path $rutaDatos 'sesion') -PathType Container) 'No se encontro una sesion WhatsApp local existente. No se iniciara ni vinculara otra automaticamente.'
$almacen = LeerValor $raizContenido 'MENSAJERO_RUTA_ALMACENAMIENTO'
if ([string]::IsNullOrWhiteSpace($almacen)) { $almacen = 'storage/app/mensajero' }
$rutaAlmacen = RutaAbsoluta $origen $almacen
Exigir (Test-Path -LiteralPath $rutaAlmacen -PathType Container) 'No se encontro la ruta multimedia del proyecto original.'

# Dos configuraciones PRIVADAS nuevas y solo en destino, nunca en Git.
$raizContenido = EstablecerValor $raizContenido 'APP_ENV' 'local'
$raizContenido = EstablecerValor $raizContenido 'APP_DEBUG' 'false'
$raizContenido = EstablecerValor $raizContenido 'APP_URL' 'http://127.0.0.1:8000'
$raizContenido = EstablecerValor $raizContenido 'MENSAJERO_MODO_AUTENTICACION' 'deshabilitado'
$raizContenido = EstablecerValor $raizContenido 'MENSAJERO_CONSULTA_LOCAL_SIN_AUTENTICACION' 'true'
$raizContenido = EstablecerValor $raizContenido 'SESSION_SECURE_COOKIE' 'false'
$raizContenido = EstablecerValor $raizContenido 'DB_ENCRYPT' 'yes'
$raizContenido = EstablecerValor $raizContenido 'DB_TRUST_SERVER_CERTIFICATE' 'true'
$raizContenido = EstablecerValor $raizContenido 'MENSAJERO_RUTA_ALMACENAMIENTO' ('"' + ($rutaAlmacen -replace '\\','/') + '"')
$recolectorContenido = EstablecerValor $recolectorContenido 'MENSAJERO_URL' 'http://127.0.0.1:8000'
$recolectorContenido = EstablecerValor $recolectorContenido 'RECOLECTOR_RUTA_DATOS' ('"' + ($rutaDatos -replace '\\','/') + '"')
$recolectorContenido = EstablecerValor $recolectorContenido 'RECOLECTOR_RUTA_ALMACENAMIENTO' ('"' + ($rutaAlmacen -replace '\\','/') + '"')

# Solo se llega aqui si toda la validacion previa fue correcta.
$utf8 = New-Object System.Text.UTF8Encoding($false)
[IO.File]::WriteAllText($destinoEnv,$raizContenido,$utf8)
try {
    [IO.File]::WriteAllText($destinoRecolectorEnv,$recolectorContenido,$utf8)
} catch {
    Remove-Item -LiteralPath $destinoEnv -Force
    throw
}
$raizContenido = $null
$recolectorContenido = $null

# Verificacion SQL sin contrasenas y SIN escrituras.
$env:PATH = (Split-Path -Parent $php74) + ';' + $env:PATH
Push-Location $destino
try {
    & $php74 artisan config:clear | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'No se pudo limpiar la cache local Laravel.' }
    $seguro = & $php74 artisan tinker --execute="echo 'BD='.config('database.connections.sqlsrv.database').';HOST='.config('database.connections.sqlsrv.host').';MODO='.config('mensajero.autenticacion.modo').PHP_EOL;"
    if ($LASTEXITCODE -ne 0) { throw 'No se pudo cargar la configuracion Laravel del clon.' }
    $esperado = "BD=mensajero_test;HOST=$dbHost;MODO=deshabilitado"
    Exigir ((@($seguro) -join '').Trim() -ceq $esperado) 'La configuracion efectiva no es la base local autorizada o el modo POC.'
    Write-Host 'PHP 7.4 / SQL LOCAL / WHATSAPP EXISTENTE: CONFIGURADOS.' -ForegroundColor Green
    Write-Host 'No se ha iniciado ningun servicio ni enviado nada a Gemini.'
    Write-Host 'Ahora iniciar el lanzador en esta misma consola:'
    Write-Host 'powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\scripts\mensajero-local.ps1 -Accion iniciar'
} finally {
    Pop-Location
}
