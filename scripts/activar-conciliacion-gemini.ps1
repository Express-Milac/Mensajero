# Autoriza recuperación controlada de mensajes NUEVOS tras este instante.
# No migra BD, no inicia trabajadores ni solicita interpretaciones a Google.
[CmdletBinding()]
param(
    [ValidateRange(1,30)][int] $MaximoDiarioPorGrupo = 10
)
$ErrorActionPreference = 'Stop'
$raiz = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$rutaEnv = Join-Path $raiz '.env'
if (-not (Test-Path -LiteralPath $rutaEnv -PathType Leaf)) {
    throw 'Falta .env privado en la carpeta Mensajero.'
}

Push-Location $raiz
try {
    $existe = (& php artisan tinker --execute="echo \Illuminate\Support\Facades\Schema::hasTable('mensajero_control_publicaciones_gemini') ? 'SI' : 'NO';")
    if ($LASTEXITCODE -ne 0 -or (@($existe) -join '').Trim() -ne 'SI') {
        throw 'Falta migración de control de publicaciones en SQL Server. No se modificó configuración.'
    }

    $politica = (& php artisan tinker --execute="echo app(\App\Services\IA\PoliticaEnvioGemini::class)->configuracionCompleta() ? 'SI' : 'NO';")
    if ($LASTEXITCODE -ne 0 -or (@($politica) -join '').Trim() -ne 'SI') {
        throw 'La política Gemini no está completamente autorizada. No se modificó configuración.'
    }

    $lista = (& php artisan tinker --execute="echo implode(',', config('mensajero.ia.grupos_autorizados'));")
    if ($LASTEXITCODE -ne 0) {
        throw 'No se pudo leer la lista de grupos autorizados.'
    }
    $lista = (@($lista) -join '').Trim()

    Write-Host ('Grupos autorizados para Gemini: ' + $lista) -ForegroundColor Yellow
    Write-Host 'La recuperación comienza solo a partir de un CORTE NUEVO, establecido ahora.' -ForegroundColor Yellow
    Write-Host ('Cupo total por grupo y por día (normalización, manual y conciliación): ' + $MaximoDiarioPorGrupo)
    Write-Host 'No se intentarán automáticamente publicaciones fallidas o inciertas.'
    Write-Host 'El script NO transmite contenido a Google; habilita una tarea futura cada cinco minutos.'
    $respuesta = Read-Host 'Para autorizar la conciliación continua y los costos futuros escriba AUTORIZO CONCILIACION'
    if ($respuesta -cne 'AUTORIZO CONCILIACION') {
        throw 'No se activó la conciliación.'
    }

    $inicio = (Get-Date).ToString('yyyy-MM-ddTHH:mm:sszzz')
    $texto = [System.IO.File]::ReadAllText($rutaEnv)
    $valores = [ordered]@{
        'MENSAJERO_IA_CONCILIACION_AUTOMATICA' = 'true'
        'MENSAJERO_IA_CONCILIACION_FECHA_INICIO' = $inicio
        'MENSAJERO_IA_CONCILIACION_MAXIMO_DIA_GRUPO' = [string]$MaximoDiarioPorGrupo
        'MENSAJERO_IA_CONCILIACION_ANTIGUEDAD_SEGUNDOS' = '180'
        'MENSAJERO_IA_CONCILIACION_HORAS_MAXIMAS' = '4'
    }

    foreach ($par in $valores.GetEnumerator()) {
        $patron = '(?m)^' + [regex]::Escape([string] $par.Key) + '=.*$'
        $linea = $par.Key + '=' + [string]$par.Value
        if ([regex]::IsMatch($texto, $patron)) {
            $texto = [regex]::Replace($texto, $patron,
                [System.Text.RegularExpressions.MatchEvaluator] { param($m) return $linea })
        } else {
            $texto = $texto.TrimEnd() + [Environment]::NewLine + $linea + [Environment]::NewLine
        }
    }

    $destino = Join-Path $env:LOCALAPPDATA 'Mensajero\respaldos'
    New-Item -ItemType Directory -Force -Path $destino | Out-Null
    $respaldo = Join-Path $destino ('conciliacion-env-' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '.bak')
    Copy-Item -LiteralPath $rutaEnv -Destination $respaldo -Force
    $temporal = $rutaEnv + '.conciliacion-temporal'
    $utf8 = New-Object System.Text.UTF8Encoding($false)
    try {
        [System.IO.File]::WriteAllText($temporal, $texto, $utf8)
        Move-Item -LiteralPath $temporal -Destination $rutaEnv -Force
    } finally {
        Remove-Item -LiteralPath $temporal -Force -ErrorAction SilentlyContinue
    }

    & php artisan config:clear
    if ($LASTEXITCODE -ne 0) {
        throw 'Se guardó .env, pero no se pudo limpiar la caché de configuración.'
    }

    Write-Host ('Conciliación autorizada para mensajes recibidos desde: ' + $inicio) -ForegroundColor Green
    Write-Host ('Respaldo privado de .env: ' + $respaldo)
    Write-Host 'Verifica schedule:list y conciliar-gemini en modo simulación.'
    Write-Host 'Después reinicia los trabajadores PHP de manera ordenada; no dupliques el recolector de WhatsApp.'
} finally {
    Pop-Location
}
