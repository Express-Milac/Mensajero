# Reinicia solo el PROCESO php queue:work mensajero_ia del supervisor local.
# No detiene recolector, web, programador ni normalizador.
# Sin -Aplicar: diagnóstico de solo lectura.
[CmdletBinding()]
param([switch] $Aplicar)

$ErrorActionPreference = 'Stop'
$raiz = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$envRuta = Join-Path $raiz '.env'
if (-not (Test-Path -LiteralPath $envRuta -PathType Leaf)) {
    throw 'No existe configuración local .env.'
}

$procesos = @(Get-CimInstance Win32_Process)
$trabajadores = @($procesos | Where-Object {
    $_.Name -eq 'php.exe' -and
    ([string]$_.CommandLine) -match 'artisan\s+queue:work' -and
    ([string]$_.CommandLine) -match 'mensajero_ia'
})
if ($trabajadores.Count -ne 1) {
    throw ('Se esperaba exactamente un trabajador IA, encontrados: ' + $trabajadores.Count + '. No se detuvo ningún proceso.')
}
$trabajador = $trabajadores[0]

# Verificar que el trabajador sea descendiente del supervisor propio y
# que el supervisor corresponda a la misma raíz del proyecto.
$porPid = @{}
foreach ($proceso in $procesos) {
    $porPid[[int]$proceso.ProcessId] = $proceso
}
$padreId = [int]$trabajador.ParentProcessId
$supervisado = $false
for ($i = 0; $i -lt 8 -and $padreId -gt 0; $i++) {
    if (-not $porPid.ContainsKey($padreId)) { break }
    $padre = $porPid[$padreId]
    $linea = [string]$padre.CommandLine
    if ($linea -match 'mensajero-local\.ps1' -and
        $linea -match '\-Servicio\s+gemini' -and
        $linea.Contains($raiz)) {
        $supervisado = $true
        break
    }
    $padreId = [int]$padre.ParentProcessId
}
if (-not $supervisado) {
    throw 'El trabajador no es un descendiente verificado del supervisor local Gemini. No se toca.'
}

$procesoPHP = Get-Process -Id $trabajador.ProcessId -ErrorAction Stop
$iniciado = $procesoPHP.StartTime
$archivos = @(
    $envRuta,
    (Join-Path $raiz 'app\Services\IA\PoliticaEnvioGemini.php'),
    (Join-Path $raiz 'app\Services\IA\ControlInterpretacionGemini.php'),
    (Join-Path $raiz 'app\Services\IA\ProcesarAnalisisGemini.php'),
    (Join-Path $raiz 'app\Services\Normalizacion\NormalizarEventoWhatsapp.php'),
    (Join-Path $raiz 'app\Jobs\AnalizarMensajeGeminiTrabajo.php')
)
$modificado = ($archivos | ForEach-Object {
    (Get-Item -LiteralPath $_ -ErrorAction Stop).LastWriteTime
} | Sort-Object -Descending | Select-Object -First 1)

Write-Host ('Trabajador Gemini: PID '+ $trabajador.ProcessId)
Write-Host ('Iniciado: ' + $iniciado.ToString('yyyy-MM-dd HH:mm:ss'))
Write-Host ('Configuración/código actualizado: ' + $modificado.ToString('yyyy-MM-dd HH:mm:ss'))
Write-Host 'Supervisor verificado. El recolector WhatsApp no se modificará.'

if (-not $Aplicar) {
    Write-Host 'Simulación: sin detener procesos. Añade -Aplicar para reiniciar solo el PHP Gemini.'
    exit 0
}

# No detener un trabajador que pueda estar ejecutando una llamada facturable.
# Se consulta el Redis efectivo de Laravel (sin acceder a mensajes ni claves).
Push-Location $raiz
try {
    $expresion = @'
echo json_encode([
    'cola' => config('mensajero.ia.cola'),
    'pendientes' => \Illuminate\Support\Facades\Redis::connection()->llen('queues:'.config('mensajero.ia.cola')),
    'reservados' => \Illuminate\Support\Facades\Redis::connection()->zcard('queues:'.config('mensajero.ia.cola').':reserved'),
    'retrasados' => \Illuminate\Support\Facades\Redis::connection()->zcard('queues:'.config('mensajero.ia.cola').':delayed')
], JSON_THROW_ON_ERROR);
'@
    $resultado = & php artisan tinker "--execute=$expresion"
    if ($LASTEXITCODE -ne 0) {
        throw 'No pudo comprobarse la cola Redis. No se detuvo ningún proceso.'
    }
    try {
        $estado = (@($resultado) -join '').Trim() | ConvertFrom-Json -ErrorAction Stop
    } catch {
        throw 'Respuesta Redis ilegible. No se detuvo ningún proceso.'
    }
    if ($estado.cola -ne 'mensajero_ia' -or
        [int]$estado.pendientes -gt 0 -or [int]$estado.reservados -gt 0 -or
        [int]$estado.retrasados -gt 0) {
        throw 'Hay trabajos Gemini en Redis o se usa otra cola. No se detuvo ningún proceso.'
    }
} finally {
    Pop-Location
}

if ($iniciado -gt $modificado) {
    Write-Host 'El trabajador ya inició después de los cambios. No es necesario reiniciarlo.'
    exit 0
}

# Comprobar que el PID siga perteneciendo al mismo proceso antes de detener.
$actual = Get-Process -Id $trabajador.ProcessId -ErrorAction Stop
if ($actual.StartTime -ne $iniciado) {
    throw 'Cambió la identidad del proceso PHP. No se detuvo ningún proceso.'
}

Stop-Process -Id $trabajador.ProcessId -ErrorAction Stop
Write-Host 'Se detuvo solo el PHP Gemini. El supervisor lanzará otro automáticamente.' -ForegroundColor Green
Write-Host 'Ejecuta scripts/diagnosticar-trabajador-gemini.ps1 y verifica que el PID y hora cambiaron.'
