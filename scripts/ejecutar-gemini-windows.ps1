# Trabajador continuo de Gemini para Windows; detener con Ctrl+C.
# Ejecutar una sola instancia. La configuración y clave se leen de .env.
[CmdletBinding()]
param()
$ErrorActionPreference = 'Stop'
$raiz = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$certificado = Join-Path $env:LOCALAPPDATA 'Mensajero\certificados\cacert.pem'
if (-not (Test-Path -LiteralPath $certificado -PathType Leaf)) {
    throw "Falta el certificado CA verificado: $certificado"
}
if (-not (Test-Path -LiteralPath (Join-Path $raiz '.env') -PathType Leaf)) {
    throw 'Falta el archivo .env local.'
}

Push-Location $raiz
try {
    # Evitar heredar las variables temporales de pruebas anteriores.
    foreach ($nombre in @(
        'CLAVE_API_GEMINI','MODELO_GEMINI','IA_HABILITADA',
        'MENSAJERO_IA_ENVIO_AUTORIZADO','MENSAJERO_IA_SERVICIO_PAGADO_CONFIRMADO',
        'MENSAJERO_IA_EJECUCION_AUTOMATICA','MENSAJERO_IA_SOLO_MENSAJE_OBJETIVO',
        'MENSAJERO_IA_GRUPOS_AUTORIZADOS','MENSAJERO_IA_FECHA_INICIO','MENSAJERO_COLA_IA'
    )) {
        Remove-Item ("Env:" + [char]92 + $nombre) -ErrorAction SilentlyContinue
    }

    Write-Host 'Trabajador continuo Mensajero/Gemini. Detener con Ctrl+C.' -ForegroundColor Cyan
    Write-Host 'Solo procesa mensajero_ia, con SSL validado. No se muestra la clave.'

    $argumentos = @(
        '-d', 'variables_order=EGPCS',
        '-d', "curl.cainfo=$certificado",
        '-d', "openssl.cafile=$certificado",
        'artisan', 'queue:work', 'redis',
        '--queue=mensajero_ia', '--sleep=3', '--tries=3', '--timeout=45',
        '--max-time=3600'
    )

    while ($true) {
        & php @argumentos
        if ($LASTEXITCODE -ne 0) {
            Write-Warning "El trabajador IA terminó con código $LASTEXITCODE; se reiniciará."
        }
        Start-Sleep -Seconds 10
    }
} finally {
    Pop-Location
}
