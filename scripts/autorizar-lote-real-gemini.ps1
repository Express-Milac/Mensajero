# Habilita análisis MANUAL de mensajes REALES ya guardados en un grupo.
# Sin cambios en SQL, sin llamada a Google, sin arranque ni parada de servicios.
# Requiere autorización explícita de MILAC para transferir texto y contexto a Google.
[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [ValidateRange(1,2147483647)]
    [int] $Grupo,

    [Parameter(Mandatory = $true)]
    [string] $Desde
)

$ErrorActionPreference = 'Stop'
$raiz = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$rutaEnv = Join-Path $raiz '.env'
if (-not (Test-Path -LiteralPath $rutaEnv -PathType Leaf)) {
    throw 'No existe .env de Mensajero.'
}

try {
    $inicio = [DateTimeOffset]::ParseExact(
        $Desde, 'yyyy-MM-ddTHH:mm:sszzz',
        [System.Globalization.CultureInfo]::InvariantCulture)
} catch {
    throw 'Usa -Desde con fecha ISO completa, ejemplo 2026-10-24T09:15:40-06:00.'
}

if ($inicio -gt [DateTimeOffset]::Now -or $inicio -lt [DateTimeOffset]::Now.AddHours(-24)) {
    throw 'La ventana para autorizar el historial debe estar entre las últimas 24 horas y ahora.'
}

Push-Location $raiz
try {
    & php artisan mensajero:comprobar-grupos-ia "--grupos=$Grupo"
    if ($LASTEXITCODE -ne 0) {
        throw 'El grupo no está habilitado para captura y tablero; no se modificó .env.'
    }

    $texto = [System.IO.File]::ReadAllText($rutaEnv)

    function VariableEnv([string] $nombre, [string] $defecto = '') {
        $match = [regex]::Match($texto, '(?m)^' + [regex]::Escape($nombre) + '=(.*)$')
        if ($match.Success) { return $match.Groups[1].Value.Trim().Trim('"').Trim("'") }
        return $defecto
    }

    $clave = VariableEnv 'CLAVE_API_GEMINI'
    $consentimiento = VariableEnv 'MENSAJERO_IA_ENVIO_AUTORIZADO'
    if ($clave.Length -lt 10 -or $consentimiento -ne 'true') {
        throw 'Falta configuración y consentimiento base de Gemini. No se modificó .env.'
    }

    $corteGlobalTexto = VariableEnv 'MENSAJERO_IA_FECHA_INICIO'
    try {
        $corteGlobal = [DateTimeOffset]::Parse($corteGlobalTexto)
    } catch {
        throw 'No se encontró corte global válido de Gemini.'
    }
    if ($inicio -lt $corteGlobal) {
        throw 'El inicio del historial es anterior al corte global. No se autoriza retroceder el consentimiento global.'
    }

    $gruposActuales = @((VariableEnv 'MENSAJERO_IA_GRUPOS_AUTORIZADOS').Split(',') |
        Where-Object { $_ -match '^[1-9][0-9]*$' } | ForEach-Object { [int]$_ })
    $gruposNuevos = @($gruposActuales + $Grupo | Sort-Object -Unique)
    if ($gruposNuevos.Count -gt 6) {
        throw 'La política IA admite como máximo seis grupos.'
    }

    $cortes = [ordered]@{}
    $jsonPrevio = VariableEnv 'MENSAJERO_IA_FECHAS_INICIO_GRUPOS' '{}'
    try {
        $parseado = ConvertFrom-Json -InputObject $jsonPrevio
        if ($null -ne $parseado) {
            foreach ($propiedad in $parseado.PSObject.Properties) {
                $cortes[$propiedad.Name] = [string]$propiedad.Value
            }
        }
    } catch {
        throw 'El JSON previo de cortes por grupo es inválido. No se modifica .env.'
    }

    $cortes[[string]$Grupo] = $inicio.ToString('yyyy-MM-ddTHH:mm:sszzz')
    $jsonCortes = ConvertTo-Json -InputObject $cortes -Compress -Depth 3

    Write-Host ''
    Write-Host ('Grupo real seleccionado: ' + $Grupo) -ForegroundColor Yellow
    Write-Host ('Historial autorizado desde: ' + $inicio.ToString('yyyy-MM-ddTHH:mm:sszzz')) -ForegroundColor Yellow
    Write-Host ('Grupos que conservarán permiso base Gemini: ' + ($gruposNuevos -join ','))
    Write-Host 'Se desactivarán temporalmente la ejecución automática y la conciliación automática de IA.'
    Write-Host 'El contenido real y hasta seis textos/leyendas del mismo grupo y ventana de 5 min'
    Write-Host 'podrían enviarse a Google al PUBLICAR luego el lote. No se envían archivos visuales.'
    Write-Host 'El comando de autorización NO publicará trabajos ni enviará mensajes.'
    Write-Host ''
    $respuesta = Read-Host 'Si tienes autorización de MILAC para enviar este historial y su contexto a Google, escribe AUTORIZO HISTORIAL REAL'
    if ($respuesta -cne 'AUTORIZO HISTORIAL REAL') {
        throw 'Cancelado sin cambiar permisos.'
    }

    $ajustes = [ordered]@{
        'MENSAJERO_IA_GRUPOS_AUTORIZADOS' = ($gruposNuevos -join ',')
        'MENSAJERO_IA_FECHAS_INICIO_GRUPOS' = ("'" + $jsonCortes + "'")
        'MENSAJERO_IA_SOLO_MENSAJE_OBJETIVO' = 'false'
        'MENSAJERO_IA_EJECUCION_AUTOMATICA' = 'false'
        'MENSAJERO_IA_CONCILIACION_AUTOMATICA' = 'false'
    }

    foreach ($ajuste in $ajustes.GetEnumerator()) {
        $patron = '(?m)^' + [regex]::Escape([string]$ajuste.Key) + '=.*$'
        $linea = [string]$ajuste.Key + '=' + [string]$ajuste.Value
        if ([regex]::IsMatch($texto, $patron)) {
            $texto = [regex]::Replace($texto, $patron,
                [System.Text.RegularExpressions.MatchEvaluator] { param($match) return $linea })
        } else {
            $texto = $texto.TrimEnd() + [Environment]::NewLine + $linea + [Environment]::NewLine
        }
    }

    $dirRespaldo = Join-Path $env:LOCALAPPDATA 'Mensajero\respaldos'
    [void][System.IO.Directory]::CreateDirectory($dirRespaldo)
    $respaldo = Join-Path $dirRespaldo ('gemini-historial-' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '.bak')
    Copy-Item -LiteralPath $rutaEnv -Destination $respaldo -Force

    $temporal = $rutaEnv + '.historial-temporal'
    try {
        [System.IO.File]::WriteAllText($temporal, $texto, (New-Object System.Text.UTF8Encoding($false)))
        Move-Item -LiteralPath $temporal -Destination $rutaEnv -Force
    } finally {
        Remove-Item -LiteralPath $temporal -Force -ErrorAction SilentlyContinue
    }

    & php artisan config:clear
    if ($LASTEXITCODE -ne 0) {
        throw 'Permisos escritos, pero falló config:clear. No publiques hasta corregir la caché.'
    }

    Write-Host 'Autorización manual de historial real registrada.' -ForegroundColor Green
    Write-Host ('Respaldo privado de configuración: ' + $respaldo)
    Write-Host 'ANTES de publicar: php artisan queue:restart'
    Write-Host ('Vista previa: php artisan mensajero:analizar-gemini --grupo=' + $Grupo + ' --limite=3 --lote-real')
    Write-Host ('Publicar máximo tres mensajes: php artisan mensajero:analizar-gemini --grupo=' + $Grupo + ' --limite=3 --lote-real --aplicar')
} finally {
    Pop-Location
}
