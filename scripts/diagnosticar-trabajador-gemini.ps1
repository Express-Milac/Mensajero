# Diagnóstico de solo lectura para trabajador Redis de Gemini en Windows.
# Nunca muestra tokens, texto de WhatsApp, JID ni clave API.
[CmdletBinding()]
param()
$ErrorActionPreference = 'Stop'
$raiz = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$archivos = @(
    (Join-Path $raiz '.env'),
    (Join-Path $raiz 'app\Services\IA\PoliticaEnvioGemini.php'),
    (Join-Path $raiz 'app\Services\IA\ControlInterpretacionGemini.php'),
    (Join-Path $raiz 'app\Services\IA\ProcesarAnalisisGemini.php'),
    (Join-Path $raiz 'app\Services\Normalizacion\NormalizarEventoWhatsapp.php'),
    (Join-Path $raiz 'app\Jobs\AnalizarMensajeGeminiTrabajo.php')
)
foreach ($ruta in $archivos) {
    if (-not (Test-Path -LiteralPath $ruta -PathType Leaf)) {
        throw "Falta un archivo necesario para diagnóstico en el proyecto."
    }
}

$ultimoCambio = ($archivos | ForEach-Object { (Get-Item -LiteralPath $_).LastWriteTime } |
    Sort-Object -Descending | Select-Object -First 1)

# Solo inspección del proceso, sin leer su entorno ni sus argumentos privados.
$trabajadores = @(Get-CimInstance Win32_Process |
    Where-Object {
        $_.Name -match '^php(?:\.exe)?$' -and
        [string]$_.CommandLine -match 'artisan\s+queue:work' -and
        [string]$_.CommandLine -match 'mensajero_ia'
    })

Write-Host ('Última modificación de configuración/código IA: ' + $ultimoCambio.ToString('yyyy-MM-dd HH:mm:ss'))
if ($trabajadores.Count -eq 0) {
    Write-Warning 'No se encontró un proceso PHP queue:work para mensajero_ia.'
    Write-Host 'El PID de la ventana supervisora por sí solo NO prueba que el trabajador exista.'
    exit 1
}

foreach ($trabajador in $trabajadores) {
    $proceso = Get-Process -Id $trabajador.ProcessId -ErrorAction SilentlyContinue
    if ($null -eq $proceso) {
        Write-Warning ('El trabajador PID ' + $trabajador.ProcessId + ' terminó durante el diagnóstico.')
        continue
    }

    $inicio = $proceso.StartTime
    $nuevo = $inicio -gt $ultimoCambio
    Write-Host ('Trabajador PHP PID: ' + $trabajador.ProcessId)
    Write-Host ('Inició: ' + $inicio.ToString('yyyy-MM-dd HH:mm:ss'))
    Write-Host ('Más reciente que la configuración y el código: ' + $(if ($nuevo) { 'SI' } else { 'NO' }))
    if (-not $nuevo) {
        Write-Warning 'El trabajador pudo conservar la configuración anterior. No publique nuevos lotes.'
    }
}

Write-Host 'Solo se leyeron metadatos de procesos y archivos. No se modificó nada.'
