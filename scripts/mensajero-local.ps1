# Mensajero MILAC: arranque, estado y parada del entorno LOCAL Windows.
# No se instala como servicio de Windows ni sobrevive a la suspension.
[CmdletBinding()]
param(
    [ValidateSet('iniciar', 'estado', 'detener')]
    [string] $Accion = 'iniciar',

    # Uso interno exclusivo para ventanas del lanzador.
    [string] $Servicio = '',
    [string] $Marca = ''
)

$ErrorActionPreference = 'Stop'
$raiz = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$recolector = Join-Path $raiz 'recolector-whatsapp'
$nombreScript = 'mensajero-local.ps1'

function LimpiarVariablesTemporales {
    foreach ($nombre in @(
        'CLAVE_API_GEMINI','MODELO_GEMINI','IA_HABILITADA',
        'MENSAJERO_IA_ENVIO_AUTORIZADO','MENSAJERO_IA_SERVICIO_PAGADO_CONFIRMADO',
        'MENSAJERO_IA_EJECUCION_AUTOMATICA','MENSAJERO_IA_SOLO_MENSAJE_OBJETIVO',
        'MENSAJERO_IA_GRUPOS_AUTORIZADOS','MENSAJERO_IA_FECHA_INICIO','MENSAJERO_COLA_IA',
        'MENSAJERO_NORMALIZACION_HABILITADA'
    )) {
        Remove-Item ('Env:' + [char]92 + $nombre) -ErrorAction SilentlyContinue
    }
}

# Ejecutado solamente en las ventanas que abre el lanzador.
if ($Servicio -ne '') {
    if ($Servicio -notin @('web','programador','normalizador','gemini','recolector')) {
        throw 'Nombre de servicio interno no permitido.'
    }
    if ($Marca -notmatch '^[a-f0-9]{32}$') {
        throw 'El proceso interno requiere una marca de lanzamiento valida.'
    }

    LimpiarVariablesTemporales
    Set-Location $raiz
    $Host.UI.RawUI.WindowTitle = 'Mensajero MILAC - ' + $Servicio
    Write-Host ('Mensajero MILAC / ' + $Servicio + ' / Ctrl+C para detener esta ventana') -ForegroundColor Cyan

    switch ($Servicio) {
        'web' {
            while ($true) {
                & php artisan serve --host=127.0.0.1 --port=8000 --no-reload
                Write-Warning 'El servidor web se cerro; se intentara levantar nuevamente.'
                Start-Sleep -Seconds 5
            }
        }
        'programador' {
            while ($true) {
                & php artisan schedule:work
                Write-Warning 'El programador se cerro; se intentara levantar nuevamente.'
                Start-Sleep -Seconds 5
            }
        }
        'normalizador' {
            while ($true) {
                & php artisan queue:work redis --queue=mensajero_normalizacion --sleep=2 --tries=5 --timeout=60 --max-time=3600
                Write-Warning 'El normalizador se cerro; se intentara levantar nuevamente.'
                Start-Sleep -Seconds 5
            }
        }
        'gemini' {
            # Este script ya supervisa los ciclos del trabajador y configura CA HTTPS.
            & (Join-Path $PSScriptRoot 'ejecutar-gemini-windows.ps1')
        }
        'recolector' {
            Set-Location $recolector
            while ($true) {
                & npm.cmd run desarrollo
                Write-Warning 'El recolector se cerro; se intentara levantar nuevamente.'
                Start-Sleep -Seconds 10
            }
        }
    }
    exit 0
}

$directorio = Join-Path $env:LOCALAPPDATA 'Mensajero\supervisor'
[void][System.IO.Directory]::CreateDirectory($directorio)
$bytes = [System.Security.Cryptography.SHA256]::Create().ComputeHash(
    [System.Text.Encoding]::UTF8.GetBytes($raiz.ToLowerInvariant())
)
$hash = [System.BitConverter]::ToString($bytes).Replace('-', '').Substring(0, 16)
$registro = Join-Path $directorio ("local-" + $hash + ".json")
$mutex = [System.Threading.Mutex]::new($false, ('Local\MensajeroMILAC-' + $hash))
$bloqueo = $false

function LeerRegistro {
    if (-not (Test-Path -LiteralPath $registro)) { return $null }
    try {
        $datos = Get-Content -LiteralPath $registro -Raw -Encoding UTF8 | ConvertFrom-Json
        if ($datos.raiz -ne $raiz) { throw 'El registro pertenece a otra instalacion.' }
        return $datos
    } catch {
        throw 'Registro de procesos ilegible: revisar antes de arrancar para evitar duplicados.'
    }
}

function GuardarRegistro($datos) {
    $temporal = $registro + '.tmp'
    $datos | ConvertTo-Json -Depth 6 | Set-Content -LiteralPath $temporal -Encoding UTF8
    Move-Item -LiteralPath $temporal -Destination $registro -Force
}

function ProcesoGestionado($entrada) {
    $identificador = [int] $entrada.pid
    if ($identificador -le 0) { return $null }
    $proceso = Get-CimInstance Win32_Process -Filter ("ProcessId = " + $identificador) -ErrorAction Stop
    if ($null -eq $proceso) { return $null }
    $linea = [string] $proceso.CommandLine
    if ($linea.Contains($nombreScript) -and $linea.Contains([string] $entrada.marca)) {
        return $proceso
    }
    return $null
}

function Resumen($datos) {
    if ($null -eq $datos) {
        Write-Host 'Sin servicios registrados por este lanzador.'
        return
    }
    foreach ($entrada in @($datos.servicios)) {
        $proceso = ProcesoGestionado $entrada
        $estado = if ($null -ne $proceso) { 'ACTIVO' } else { 'DETENIDO' }
        Write-Host ('{0,-16} {1,-12} PID {2}' -f $entrada.nombre, $estado, $entrada.pid)
    }
}

function DetenerArbol($raizPid) {
    $todos = @(Get-CimInstance Win32_Process -ErrorAction Stop)
    $descendientes = New-Object System.Collections.Generic.List[int]

    function AgregarHijos([int]$padre) {
        foreach ($proceso in $todos) {
            if ([int]$proceso.ParentProcessId -eq $padre) {
                AgregarHijos ([int]$proceso.ProcessId)
                $descendientes.Add([int]$proceso.ProcessId)
            }
        }
    }

    AgregarHijos ([int]$raizPid)
    foreach ($pidHijo in $descendientes) {
        Stop-Process -Id $pidHijo -Force -ErrorAction SilentlyContinue
    }
    Stop-Process -Id ([int]$raizPid) -Force -ErrorAction SilentlyContinue
}

function ComprobarExistentes {
    $procesos = @(Get-CimInstance Win32_Process -ErrorAction Stop)
    $patrones = @(
        'artisan\s+schedule:work',
        'artisan\s+queue:work.*mensajero_normalizacion',
        'artisan\s+queue:work.*mensajero_ia',
        'ejecutar-gemini-windows\.ps1',
        'src[/\\]principal\.ts',
        'dist[/\\]src[/\\]principal\.js'
    )
    $posibles = @($procesos | Where-Object {
        $cmd = [string] $_.CommandLine
        if (-not $cmd) { return $false }
        foreach ($patron in $patrones) {
            if ($cmd -match $patron) { return $true }
        }
        return $false
    })
    if ($posibles.Count -gt 0) {
        $ids = ($posibles | ForEach-Object { $_.ProcessId } | Sort-Object -Unique) -join ','
        throw ("Se detectaron procesos antiguos o externos (PID: $ids). Cierralos manualmente y vuelve a iniciar; no se duplicaran trabajadores ni WhatsApp.")
    }

    # No apropiarse de otro servidor que use el puerto del POC.
    $conexion = New-Object System.Net.Sockets.TcpClient
    try {
        $intento = $conexion.BeginConnect('127.0.0.1', 8000, $null, $null)
        if ($intento.AsyncWaitHandle.WaitOne(600)) {
            try { $conexion.EndConnect($intento) } catch {}
            if ($conexion.Connected) {
                throw 'El puerto local 8000 ya esta ocupado. Cierra el servidor anterior antes de usar el lanzador.'
            }
        }
    } finally {
        $conexion.Dispose()
    }
}

function VerificarRedis {
    $salida = & wsl.exe -d Debian -- redis-cli ping 2>$null
    if ((@($salida) -join ' ').Trim() -eq 'PONG') { return }
    Write-Host 'Redis no responde; intento iniciar redis-server en WSL Debian.'
    & wsl.exe -d Debian -u root -- service redis-server start | Out-Null
    $salida = & wsl.exe -d Debian -- redis-cli ping 2>$null
    if ((@($salida) -join ' ').Trim() -ne 'PONG') {
        throw 'Redis no responde en WSL Debian. No se iniciaron los servicios Laravel.'
    }
}

function ValidarConfiguracion {
    if (-not (Test-Path -LiteralPath (Join-Path $raiz '.env'))) { throw 'Falta .env de Laravel.' }
    if (-not (Test-Path -LiteralPath (Join-Path $recolector '.env'))) { throw 'Falta .env del recolector.' }
    if (-not (Test-Path -LiteralPath (Join-Path $raiz 'server.php') -PathType Leaf)) { throw 'Falta server.php requerido por artisan serve de Laravel 8. Actualice la rama PHP74.' }
    if (-not (Test-Path -LiteralPath (Join-Path $raiz 'vendor\autoload.php'))) { throw 'Falta vendor; ejecutar composer install.' }
    if (-not (Test-Path -LiteralPath (Join-Path $recolector 'node_modules'))) { throw 'Falta node_modules del recolector; ejecutar npm install.' }
    foreach ($programa in @('php.exe', 'npm.cmd', 'wsl.exe', 'powershell.exe')) {
        if (-not (Get-Command $programa -ErrorAction SilentlyContinue)) { throw ("No se encuentra " + $programa) }
    }
    $certificado = Join-Path $env:LOCALAPPDATA 'Mensajero\certificados\cacert.pem'
    if (-not (Test-Path -LiteralPath $certificado)) { throw 'Falta el certificado CA HTTPS de Gemini.' }

    LimpiarVariablesTemporales
    Set-Location $raiz
    & php artisan config:clear | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'Fallo al limpiar configuracion Laravel.' }

    $comprobacion = & php artisan tinker --execute="echo json_encode(['normalizacion'=>config('mensajero.normalizacion.habilitada')===true,'ia'=>app(\App\Services\IA\PoliticaEnvioGemini::class)->configuracionCompleta(),'automatica'=>config('mensajero.ia.ejecucion_automatica')===true,'grupos'=>config('mensajero.ia.grupos_autorizados')],JSON_THROW_ON_ERROR);"
    if ($LASTEXITCODE -ne 0) { throw 'No se pudo comprobar la configuracion Laravel.' }
    $configuracion = (@($comprobacion) -join '').Trim() | ConvertFrom-Json

    if ($configuracion.normalizacion -ne $true) {
        throw 'MENSAJERO_NORMALIZACION_HABILITADA no esta activada en .env.'
    }
    if ($configuracion.ia -ne $true) {
        throw 'La política Gemini no está autorizada.'
    }
    if ($configuracion.automatica -ne $true) {
        Write-Host 'Gemini se ejecutara en modo manual; sin publicacion automatica.'
    }
    $gruposIA = @($configuracion.grupos)
    if ($gruposIA.Count -lt 1 -or $gruposIA.Count -gt 6) {
        throw 'Gemini necesita entre 1 y 6 grupos expresamente autorizados.'
    }
    if (@($gruposIA | Where-Object { -not ([string] $_ -match '^[1-9][0-9]+$') }).Count -ne 0) {
        throw 'La lista Gemini tiene identificadores invalidos.'
    }
    if (@($gruposIA | Sort-Object -Unique).Count -ne $gruposIA.Count) {
        throw 'La lista Gemini contiene grupos duplicados.'
    }
    $programacion = (& php artisan schedule:list | Out-String)
    if ($LASTEXITCODE -ne 0 -or -not $programacion.Contains('mensajero:despachar-buzon')) {
        throw 'El programador Laravel no tiene activado mensajero:despachar-buzon.'
    }
}

function EsperarServidorWeb {
    # Un proceso PowerShell ACTIVO no demuestra que PHP haya abierto el puerto.
    # /disponible es la ruta de vida sin SQL Server ni datos operativos.
    for ($intento = 1; $intento -le 15; $intento++) {
        try {
            $respuesta = Invoke-WebRequest -Uri 'http://127.0.0.1:8000/disponible' -UseBasicParsing -TimeoutSec 2 -ErrorAction Stop
            if ([int]$respuesta.StatusCode -eq 200) {
                Write-Host 'WEB LISTA: http://127.0.0.1:8000/tablero' -ForegroundColor Green
                return
            }
        } catch {
            # Esperar mientras el proceso web termina de arrancar.
        }
        Start-Sleep -Milliseconds 500
    }
    throw 'Laravel no respondio HTTP 200 en /disponible. Revise la ventana WEB; no se iniciara el resto de procesos.'
}

function AbrirServicio([string] $nombre, $datos) {
    $marca = [guid]::NewGuid().ToString('N')
    $argumentos = '-NoProfile -ExecutionPolicy Bypass -File "' + $PSCommandPath + '" -Servicio ' + $nombre + ' -Marca ' + $marca
    $proceso = Start-Process -FilePath 'powershell.exe' -ArgumentList $argumentos -WorkingDirectory $raiz -PassThru
    Start-Sleep -Milliseconds 500
    if ($proceso.HasExited) { throw ("El servicio " + $nombre + " se cerro al iniciar.") }
    $datos.servicios += [pscustomobject]@{ nombre = $nombre; pid = $proceso.Id; marca = $marca }
    GuardarRegistro $datos
    Write-Host ('Iniciado: {0,-16} PID {1}' -f $nombre, $proceso.Id) -ForegroundColor Green
}

try {
    $bloqueo = $mutex.WaitOne(0)
    if (-not $bloqueo) { throw 'Hay otro comando Mensajero operando. No se duplican inicios.' }
    $datosActuales = LeerRegistro

    switch ($Accion) {
        'estado' {
            Resumen $datosActuales
            Write-Host 'Diagnostico por grupo: php artisan mensajero:estado-gemini --grupo=ID'
            break
        }
        'detener' {
            if ($null -eq $datosActuales) {
                Write-Host 'No hay servicios propios registrados para detener.'
                break
            }
            foreach ($entrada in @($datosActuales.servicios) | Sort-Object -Property nombre -Descending) {
                $proceso = ProcesoGestionado $entrada
                if ($null -ne $proceso) {
                    DetenerArbol ([int] $proceso.ProcessId)
                    Write-Host ('Detenido: ' + $entrada.nombre)
                }
            }
            Remove-Item -LiteralPath $registro -Force -ErrorAction SilentlyContinue
            Write-Host 'Servicios gestionados detenidos. Redis WSL no se apaga (puede ser compartido).'
            break
        }
        'iniciar' {
            if ($null -ne $datosActuales) {
                $activos = @($datosActuales.servicios | Where-Object { $null -ne (ProcesoGestionado $_) })
                if ($activos.Count -gt 0) {
                    Write-Host 'Mensajero ya tiene servicios iniciados por este lanzador; no se duplicaran.' -ForegroundColor Yellow
                    Resumen $datosActuales
                    break
                }
                Remove-Item -LiteralPath $registro -Force -ErrorAction SilentlyContinue
            }

            ComprobarExistentes
            ValidarConfiguracion
            VerificarRedis

            $nuevos = [pscustomobject]@{
                raiz = $raiz
                iniciado_en = [DateTimeOffset]::Now.ToString('o')
                servicios = @()
            }
            try {
                AbrirServicio 'web' $nuevos
                EsperarServidorWeb
                AbrirServicio 'programador' $nuevos
                AbrirServicio 'normalizador' $nuevos
                AbrirServicio 'gemini' $nuevos
                AbrirServicio 'recolector' $nuevos
            } catch {
                Write-Warning 'Hubo un fallo durante el inicio. Se cierran solo los servicios iniciados por este lanzador.'
                foreach ($entrada in @($nuevos.servicios)) {
                    $proceso = ProcesoGestionado $entrada
                    if ($null -ne $proceso) { DetenerArbol ([int]$proceso.ProcessId) }
                }
                Remove-Item -LiteralPath $registro -Force -ErrorAction SilentlyContinue
                throw
            }

            Write-Host ''
            Write-Host 'Mensajero LOCAL arrancado. http://127.0.0.1:8000/tablero' -ForegroundColor Green
            Write-Host 'Estado:   powershell -NoProfile -File .\scripts\mensajero-local.ps1 -Accion estado'
            Write-Host 'Detener:  powershell -NoProfile -File .\scripts\mensajero-local.ps1 -Accion detener'
            Write-Host 'Los procesos no sobreviven a suspension/reinicio/cierre de sesion.'
            break
        }
    }
} finally {
    if ($bloqueo) { $mutex.ReleaseMutex() }
    $mutex.Dispose()
}
