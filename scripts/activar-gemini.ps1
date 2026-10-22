# Activación persistente de Gemini. No inicia procesos ni cambia datos SQL Server.
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
    throw "No existe .env en $raiz."
}

Write-Host "Activación automática: grupo interno $GrupoId (solo mensajes de texto nuevos)." -ForegroundColor Cyan
$autorizacion = Read-Host '¿MILAC autorizó este grupo, enviar texto a Google y las condiciones de datos/facturación? Escriba SI'
if ($autorizacion -cne 'SI') {
    throw 'Activación cancelada: falta autorización expresa.'
}

$claveProtegida = Read-Host 'Clave de Gemini autorizada para Mensajero' -AsSecureString
$clave = [System.Net.NetworkCredential]::new('', $claveProtegida).Password
if ([string]::IsNullOrWhiteSpace($clave) -or $clave.Length -lt 10) {
    throw 'Clave incompleta. .env permanece intacto.'
}
if ($clave.Contains([char]13) -or $clave.Contains([char]10) -or $clave.Contains('#')) {
    throw 'Clave con caracteres incompatibles con el instalador. .env permanece intacto.'
}

try {
    $texto = [System.IO.File]::ReadAllText($archivo)
    $encontrado = [regex]::Match($texto, '(?m)^MENSAJERO_IA_FECHA_INICIO=(.*)$')
    $inicioActual = if ($encontrado.Success) { $encontrado.Groups[1].Value.Trim().Trim('"', "'") } else { '' }
    # Por defecto NO retroanalizar mensajes de pilotos anteriores.
    # Al reactivar tras un corte operativo se puede preservar la fecha
    # usando -ConservarCorte, previa revisión del contenido pendiente.
    if ($ConservarCorte -and
        $inicioActual -match '^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}

    $ajustes = [ordered]@{
        'CLAVE_API_GEMINI' = $clave
        'MODELO_GEMINI' = 'gemini-3.5-flash-lite'
        'IA_HABILITADA' = 'true'
        'MENSAJERO_IA_ENVIO_AUTORIZADO' = 'true'
        'MENSAJERO_IA_SERVICIO_PAGADO_CONFIRMADO' = 'true'
        'MENSAJERO_IA_EJECUCION_AUTOMATICA' = 'true'
        'MENSAJERO_IA_SOLO_MENSAJE_OBJETIVO' = 'true'
        'MENSAJERO_NORMALIZACION_HABILITADA' = 'true'
        'MENSAJERO_IA_GRUPOS_AUTORIZADOS' = [string]$GrupoId
        'MENSAJERO_IA_FECHA_INICIO' = $inicio
        'MENSAJERO_COLA_IA' = 'mensajero_ia'
    }

    foreach ($entrada in $ajustes.GetEnumerator()) {
        $patron = '(?m)^' + [regex]::Escape($entrada.Key) + '=.*$'
        $linea = $entrada.Key + '=' + [string]$entrada.Value
        if ([regex]::IsMatch($texto, $patron)) {
            $texto = [regex]::Replace($texto, $patron, [System.Text.RegularExpressions.MatchEvaluator]{
                param($coincidencia)
                return $linea
            })
        } else {
            $texto = $texto.TrimEnd() + [Environment]::NewLine + $linea + [Environment]::NewLine
        }
    }

    Copy-Item -LiteralPath $archivo -Destination ($archivo + '.backup') -Force
    $utf8 = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllText($archivo, $texto, $utf8)
} finally {
    $clave = $null
    $claveProtegida = $null
}

# Los ajustes temporales de PowerShell tienen prioridad frente al .env.
foreach ($nombre in @(
    'CLAVE_API_GEMINI','MODELO_GEMINI','IA_HABILITADA',
    'MENSAJERO_IA_ENVIO_AUTORIZADO','MENSAJERO_IA_SERVICIO_PAGADO_CONFIRMADO',
    'MENSAJERO_IA_EJECUCION_AUTOMATICA','MENSAJERO_IA_SOLO_MENSAJE_OBJETIVO',
    'MENSAJERO_IA_GRUPOS_AUTORIZADOS','MENSAJERO_IA_FECHA_INICIO','MENSAJERO_COLA_IA','MENSAJERO_NORMALIZACION_HABILITADA'
)) {
    Remove-Item ("Env:" + [char]92 + $nombre) -ErrorAction SilentlyContinue
}

Push-Location $raiz
try {
    php artisan config:clear
    if ($LASTEXITCODE -ne 0) {
        throw 'No fue posible limpiar la configuración Laravel.'
    }
    Write-Host "Gemini configurado en .env para grupo $GrupoId." -ForegroundColor Green
    Write-Host "Corte de mensajes: $inicio"
    Write-Host 'Para que quede activo, ejecute programador, normalizador y trabajador de IA de forma supervisada.'
    Write-Host 'Esta configuración no realiza ninguna solicitud a Google.'
} finally {
    Pop-Location
}
) {
        $inicio = $inicioActual
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
        'MENSAJERO_IA_SOLO_MENSAJE_OBJETIVO' = 'true'
        'MENSAJERO_NORMALIZACION_HABILITADA' = 'true'
        'MENSAJERO_IA_GRUPOS_AUTORIZADOS' = [string]$GrupoId
        'MENSAJERO_IA_FECHA_INICIO' = $inicio
        'MENSAJERO_COLA_IA' = 'mensajero_ia'
    }

    foreach ($entrada in $ajustes.GetEnumerator()) {
        $patron = '(?m)^' + [regex]::Escape($entrada.Key) + '=.*$'
        $linea = $entrada.Key + '=' + [string]$entrada.Value
        if ([regex]::IsMatch($texto, $patron)) {
            $texto = [regex]::Replace($texto, $patron, [System.Text.RegularExpressions.MatchEvaluator]{
                param($coincidencia)
                return $linea
            })
        } else {
            $texto = $texto.TrimEnd() + [Environment]::NewLine + $linea + [Environment]::NewLine
        }
    }

    Copy-Item -LiteralPath $archivo -Destination ($archivo + '.backup') -Force
    $utf8 = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllText($archivo, $texto, $utf8)
} finally {
    $clave = $null
    $claveProtegida = $null
}

# Los ajustes temporales de PowerShell tienen prioridad frente al .env.
foreach ($nombre in @(
    'CLAVE_API_GEMINI','MODELO_GEMINI','IA_HABILITADA',
    'MENSAJERO_IA_ENVIO_AUTORIZADO','MENSAJERO_IA_SERVICIO_PAGADO_CONFIRMADO',
    'MENSAJERO_IA_EJECUCION_AUTOMATICA','MENSAJERO_IA_SOLO_MENSAJE_OBJETIVO',
    'MENSAJERO_IA_GRUPOS_AUTORIZADOS','MENSAJERO_IA_FECHA_INICIO','MENSAJERO_COLA_IA','MENSAJERO_NORMALIZACION_HABILITADA'
)) {
    Remove-Item ("Env:" + [char]92 + $nombre) -ErrorAction SilentlyContinue
}

Push-Location $raiz
try {
    php artisan config:clear
    if ($LASTEXITCODE -ne 0) {
        throw 'No fue posible limpiar la configuración Laravel.'
    }
    Write-Host "Gemini configurado en .env para grupo $GrupoId." -ForegroundColor Green
    Write-Host "Corte de mensajes: $inicio"
    Write-Host 'Para que quede activo, ejecute programador, normalizador y trabajador de IA de forma supervisada.'
    Write-Host 'Esta configuración no realiza ninguna solicitud a Google.'
} finally {
    Pop-Location
}
