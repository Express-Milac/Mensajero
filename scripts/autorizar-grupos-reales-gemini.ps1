# Configura explícitamente los grupos reales que pueden enviar textos
# y leyendas autorizadas a Gemini. Nunca envia mensajes ni inicia procesos.
[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [ValidateNotNullOrEmpty()]
    [int[]] $Grupos,

    # Opcional: seleccionar como contexto hasta seis textos o leyendas
    # del MISMO grupo posteriores al corte, maximo cinco minutos.
    [switch] $ContextoConversacional
)

$ErrorActionPreference = 'Stop'
$raiz = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$envRuta = Join-Path $raiz '.env'
if (-not (Test-Path -LiteralPath $envRuta -PathType Leaf)) {
    throw 'No existe .env privado de Mensajero.'
}
if ($Grupos.Count -lt 1 -or $Grupos.Count -gt 6 -or
    @($Grupos | Where-Object { $_ -le 0 }).Count -gt 0 -or
    @($Grupos | Sort-Object -Unique).Count -ne $Grupos.Count) {
    throw 'Debes seleccionar de uno a seis identificadores internos positivos, sin duplicados.'
}
$lista = $Grupos -join ','

Push-Location $raiz
try {
    # Esta comprobación consulta exclusivamente el catálogo de grupos,
    # nunca el contenido de WhatsApp. Si falla, no se edita .env.
    & php artisan mensajero:comprobar-grupos-ia "--grupos=$lista"
    if ($LASTEXITCODE -ne 0) {
        throw 'La verificación de grupos no fue aprobada; no se modificó .env.'
    }

    $texto = [System.IO.File]::ReadAllText($envRuta)
    foreach ($nombre in @('CLAVE_API_GEMINI','IA_HABILITADA',
        'MENSAJERO_IA_ENVIO_AUTORIZADO','MENSAJERO_IA_SERVICIO_PAGADO_CONFIRMADO')) {
        if ($texto -notmatch ("(?m)^" + [regex]::Escape($nombre) + "=")) {
            throw ("Falta configurar " + $nombre + " en .env. Usa antes activar-gemini.ps1 con el grupo piloto.")
        }
    }
    $coincidencia = [regex]::Match($texto, '(?m)^CLAVE_API_GEMINI=(.+)$')
    if (-not $coincidencia.Success -or $coincidencia.Groups[1].Value.Trim().Trim('"').Trim("'").Length -lt 10) {
        throw 'La clave Gemini no está configurada; se necesita activar la cuenta antes.'
    }

    Write-Host ''
    Write-Host ('Grupos que recibiran interpretacion Gemini: ' + $lista) -ForegroundColor Yellow
    Write-Host 'Solo los mensajes nuevos DESPUES DEL NUEVO CORTE temporal.' -ForegroundColor Yellow
    Write-Host 'Los textos y leyendas seleccionados se transmitiran a Google y pueden generar costos.'
    if ($ContextoConversacional) {
        Write-Host 'CONTEXTO: hasta seis mensajes del mismo grupo dentro de cinco minutos, no solo el mensaje objetivo.'
        Write-Host 'El contexto puede incluir textos de distintos participantes; la autorizacion debe cubrirlos.'
    } else {
        Write-Host 'CONTEXTO: solo el mensaje objetivo, sin conversación previa.'
    }
    Write-Host 'No se transmiten imagenes, audio, videos ni documentos; solo texto y leyendas.'

    $autorizado = Read-Host 'Confirma que MILAC autoriza enviar estos mensajes REALES a Google y acepta costos: escribe AUTORIZO DATOS REALES'
    if ($autorizado -cne 'AUTORIZO DATOS REALES') {
        throw 'No se modifico el entorno porque no se confirmo la autorizacion explicita.'
    }

    # Establecer corte AHORA: impide enviar antiguos mensajes ya capturados.
    # Este corte es global a la lista completa de grupos (tambien al piloto).
    $inicio = (Get-Date).ToString('yyyy-MM-ddTHH:mm:sszzz')
    $ajustes = [ordered]@{
        'IA_HABILITADA' = 'true'
        'MENSAJERO_IA_ENVIO_AUTORIZADO' = 'true'
        'MENSAJERO_IA_SERVICIO_PAGADO_CONFIRMADO' = 'true'
        'MENSAJERO_IA_EJECUCION_AUTOMATICA' = 'true'
        'MENSAJERO_IA_GRUPOS_AUTORIZADOS' = $lista
        'MENSAJERO_IA_FECHA_INICIO' = $inicio
        'MENSAJERO_IA_SOLO_MENSAJE_OBJETIVO' = $(if ($ContextoConversacional) { 'false' } else { 'true' })
    }

    foreach ($item in $ajustes.GetEnumerator()) {
        $patron = '(?m)^' + [regex]::Escape([string] $item.Key) + '=.*$'
        $linea = $item.Key + '=' + $item.Value
        if ([regex]::IsMatch($texto, $patron)) {
            $texto = [regex]::Replace(
                $texto, $patron,
                [System.Text.RegularExpressions.MatchEvaluator]{ param($m) return $linea }
            )
        } else {
            $texto = $texto.TrimEnd() + [Environment]::NewLine + $linea + [Environment]::NewLine
        }
    }

    # El backup contiene secretos: se mantiene en la cuenta local, fuera de Git.
    $respaldoDir = Join-Path $env:LOCALAPPDATA 'Mensajero\respaldos'
    [void][System.IO.Directory]::CreateDirectory($respaldoDir)
    $respaldo = Join-Path $respaldoDir ('gemini-env-' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '.bak')
    Copy-Item -LiteralPath $envRuta -Destination $respaldo -Force
    $temporal = $envRuta + '.gemini-temporal'
    $utf8 = New-Object System.Text.UTF8Encoding($false)
    try {
        [System.IO.File]::WriteAllText($temporal, $texto, $utf8)
        Move-Item -LiteralPath $temporal -Destination $envRuta -Force
    } finally {
        Remove-Item -LiteralPath $temporal -Force -ErrorAction SilentlyContinue
    }

    Write-Host ''
    Write-Host ('Autorizacion almacenada para IDs: ' + $lista) -ForegroundColor Green
    Write-Host ('Fecha de inicio: ' + $inicio)
    Write-Host 'No se consulto Google ni se publico trabajo alguno.'
    Write-Host 'IMPORTANTE: reiniciar los trabajadores normalizador y Gemini para que lean los cambios.'
    Write-Host 'No reiniciar ni duplicar el recolector WhatsApp.'
    Write-Host ('Respaldo privado: ' + $respaldo)
} finally {
    Pop-Location
}
