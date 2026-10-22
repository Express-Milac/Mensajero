# Activación persistente local de Gemini. No inicia procesos ni modifica SQL Server.
[CmdletBinding()]
param(
    [ValidateRange(1,2147483647)]
    [int] $GrupoId = 10020,
    [switch] $ConservarCorte
)

$ErrorActionPreference = 'Stop'
$raiz = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$archivo = Join-Path $raiz '.env'
if (-not (Test-Path -LiteralPath $archivo -PathType Leaf)) {
    throw "No existe el archivo .env en $raiz."
}

Write-Host "Gemini automático solo para grupo interno $GrupoId y mensajes posteriores al corte." -ForegroundColor Cyan
$autorizacion = Read-Host '¿MILAC autorizó envío de texto de este grupo a Google, facturación y tratamiento de datos? Escriba SI'
if ($autorizacion -cne 'SI') {
    throw 'Activación cancelada por falta de autorización expresa.'
}

$claveProtegida = Read-Host 'Clave API de Google Gemini autorizada' -AsSecureString
$clave = [System.Net.NetworkCredential]::new('', $claveProtegida).Password
if ([string]::IsNullOrWhiteSpace($clave) -or $clave.Length -lt 10) {
    throw 'Clave incompleta; no se modificó .env.'
}
if ($clave.Contains([char]13) -or $clave.Contains([char]10) -or $clave.Contains('#')) {
    throw 'Clave no compatible con formato .env; no se modificó nada.'
}

try {
    $texto = [System.IO.File]::ReadAllText($archivo)
    $coincidencia = [regex]::Match($texto, '(?m)^MENSAJERO_IA_FECHA_INICIO=(.*)$')
    $fechaAnterior = if ($coincidencia.Success) {
        $coincidencia.Groups[1].Value.Trim().Trim('"').Trim("'")
    } else {
        ''
    }

    # Cortar en este momento salvo petición explícita de preservar un corte validado.
    if ($ConservarCorte -and
        $fechaAnterior -match '^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$') {
        $inicio = $fechaAnterior
    } else {
        $inicio = (Get-Date).ToString('yyyy-MM-ddTHH:mm:sszzz')
    }

    $ajustes = [ordered]@{
        'CLAVE_API_GEMINI' = $clave
        'MODELO_GEMINI' = 'gemini-3.5-flash-lite'
        'IA_HABILITADA' = 'true'
        'MENSAJERO_IA_ENVIO_AUTORIZADO' = 'true'
        'MENSAJERO_IA_SERVICIO_PAGADO_CONFIRMADO' = 'true'
        'MENSAJERO_IA_EJECUCION_AUTOMATICA' = 'true'
        'MENSAJERO_NORMALIZACION_HABILITADA' = 'true'
        'MENSAJERO_IA_SOLO_MENSAJE_OBJETIVO' = 'true'
        'MENSAJERO_IA_GRUPOS_AUTORIZADOS' = [string]$GrupoId
        'MENSAJERO_IA_FECHA_INICIO' = $inicio
        'MENSAJERO_COLA_IA' = 'mensajero_ia'
    }

    foreach ($entrada in $ajustes.GetEnumerator()) {
        $patron = '(?m)^' + [regex]::Escape($entrada.Key) + '=.*$'
        $linea = $entrada.Key + '=' + [string]$entrada.Value
        if ([regex]::IsMatch($texto, $patron)) {
            $texto = [regex]::Replace(
                $texto, $patron,
                [System.Text.RegularExpressions.MatchEvaluator]{
                    param($match)
                    return $linea
                }
            )
        } else {
            $texto = $texto.TrimEnd() + [Environment]::NewLine + $linea + [Environment]::NewLine
        }
    }

    # El respaldo también contiene secretos y queda excluido de Git.
    Copy-Item -LiteralPath $archivo -Destination ($archivo + '.backup') -Force
    $utf8 = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllText($archivo, $texto, $utf8)
} finally {
    $clave = $null
    $claveProtegida = $null
}

# Las variables temporales de pruebas previas prevalecen sobre .env.
foreach ($nombre in @(
    'CLAVE_API_GEMINI','MODELO_GEMINI','IA_HABILITADA',
    'MENSAJERO_IA_ENVIO_AUTORIZADO','MENSAJERO_IA_SERVICIO_PAGADO_CONFIRMADO',
    'MENSAJERO_IA_EJECUCION_AUTOMATICA','MENSAJERO_NORMALIZACION_HABILITADA',
    'MENSAJERO_IA_SOLO_MENSAJE_OBJETIVO','MENSAJERO_IA_GRUPOS_AUTORIZADOS',
    'MENSAJERO_IA_FECHA_INICIO','MENSAJERO_COLA_IA'
)) {
    Remove-Item ('Env:' + [char]92 + $nombre) -ErrorAction SilentlyContinue
}

Push-Location $raiz
try {
    php artisan config:clear
    if ($LASTEXITCODE -ne 0) {
        throw 'No se pudo limpiar la configuración Laravel.'
    }
    Write-Host "Gemini configurado en .env; único grupo IA: $GrupoId" -ForegroundColor Green
    Write-Host "Corte autorizado: $inicio"
    Write-Host 'Para activación operativa, iniciar supervisor, recolector, programador, normalización y trabajador Gemini.'
    Write-Host 'Este script no envió solicitudes a Google.'
} finally {
    Pop-Location
}
