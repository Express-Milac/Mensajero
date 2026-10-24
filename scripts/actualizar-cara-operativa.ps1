# Actualiza solamente la primera version de la cara operativa.
# No escribe .env, no ejecuta migraciones, no envia nada a Google.
[CmdletBinding()]
param()
$ErrorActionPreference = 'Stop'
$raiz = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$ref = 'fase-2-gemini-eventos-operativos-20261021'

$rutas = @(
    'database/migrations/2026_10_23_000100_crear_acontecimientos_operativos.php',
    'database/migrations/2026_10_23_000200_crear_auditoria_operativa.php',
    'database/migrations/2026_10_24_000100_crear_control_publicaciones_gemini.php',
    'database/migrations/2026_10_24_000200_crear_control_interpretacion_ia.php',
    'app/Services/Operacion',
    'app/Services/Mensajes/PrepararEventosPanorama.php',
    'app/Services/IA/ControlInterpretacionGemini.php',
    'app/Http/Middleware/AutorizarGestionIA.php',
    'app/Http/Controllers/IA/ControladorConfiguracionGemini.php',
    'resources/views/ia/configuracion.blade.php',
    'public/css/control-gemini.css',
    'tests/Funcional/ControlGeminiWebTest.php',
    'app/Http/Controllers/Mensajes/ControladorMensajes.php',
    'resources/views/mensajes/indice.blade.php',
    'resources/views/mensajes/conversaciones.blade.php',
    'resources/views/mensajes/partes/panorama-tarjetas.blade.php',
    'public/css/panorama-eventos.css',
    'public/js/panorama.js',
    'tests/Funcional/PanoramaEventosMensajesTest.php',
    'app/Http/Controllers/Operacion',
    'app/Http/Middleware/AutorizarGestionOperacion.php',
    'app/Console/Commands/ProyectarOperacion.php',
    'app/Console/Commands/ComprobarGruposIa.php',
    'app/Console/Commands/EstadoGemini.php',
    'app/Console/Commands/DiagnosticarLoteGemini.php',
    'app/Console/Commands/ConciliarGemini.php',
    'app/Jobs/AnalizarMensajeGeminiTrabajo.php',
    'app/Services/IA/ConciliarPublicacionesGemini.php',
    'scripts/activar-conciliacion-gemini.ps1',
    'scripts/autorizar-lote-real-gemini.ps1',
    'scripts/diagnosticar-trabajador-gemini.ps1',
    'scripts/reiniciar-solo-gemini.ps1',
    'app/Services/IA/PoliticaEnvioGemini.php',
    'docs/conciliacion-gemini.md',
    'docs/piloto-mensajes-reales.md',
    'routes/console.php',
    'tests/Funcional/ConciliacionGeminiTest.php',
    'docs/autorizar-gemini-grupos-reales.md',
    'scripts/mensajero-local.ps1',
    'scripts/autorizar-grupos-reales-gemini.ps1',
    'app/Console/Commands/AnalizarGemini.php',
    'app/Services/IA/ConstruirDialogoGemini.php',
    'tests/Unidad/ConstruirDialogoGeminiTest.php',
    'app/Services/IA/ClienteGemini.php',
    'app/Services/IA/ProcesarAnalisisGemini.php',
    'app/Services/Normalizacion/NormalizarEventoWhatsapp.php',
    'app/Http/Controllers/Mensajes/ControladorArchivosWhatsapp.php',
    'resources/views/operacion',
    'resources/views/components/layout.blade.php',
    'routes/web.php',
    'bootstrap/app.php',
    'config/mensajero.php',
    '.env.example',
    'tests/Funcional/GeminiContratoTest.php',
    'tests/Funcional/OperacionAccesoTest.php',
    'tests/Integracion/AcontecimientosOperativosSqlServerTest.php',
    'docs/cara-operativa.md',
    'docs/bitacora-automatica.md',
    'docs/panorama-eventos-mensajes.md',
    'docs/canalizacion-ia.md',
    'docs/arquitectura.md',
    'docs/operacion-gemini-permanente.md',
    'README.md'
)

if (-not (Get-Command git -ErrorAction SilentlyContinue)) {
    throw 'Git no esta disponible.'
}

$destinoBase = Join-Path $env:LOCALAPPDATA (
    'Mensajero\respaldos\cara-operativa-' + (Get-Date -Format 'yyyyMMdd-HHmmss')
)

Push-Location $raiz
try {
    Write-Host 'Recuperando la rama de la cara operativa...' -ForegroundColor Cyan
    & git fetch origin $ref
    if ($LASTEXITCODE -ne 0) { throw 'No se pudo obtener la rama de GitHub.' }

    New-Item -Path $destinoBase -ItemType Directory -Force | Out-Null

    # Copiar el contenido anterior completo, incluyendo archivos no registrados
    # dentro de las carpetas que se van a sustituir.
    foreach ($ruta in $rutas) {
        $origen = Join-Path $raiz $ruta
        $destino = Join-Path $destinoBase $ruta
        if (Test-Path -LiteralPath $origen -PathType Leaf) {
            New-Item -Path (Split-Path $destino) -ItemType Directory -Force | Out-Null
            Copy-Item -LiteralPath $origen -Destination $destino -Force
        } elseif (Test-Path -LiteralPath $origen -PathType Container) {
            New-Item -Path $destino -ItemType Directory -Force | Out-Null
            Copy-Item -Path (Join-Path $origen '*') -Destination $destino -Recurse -Force
        }
    }

    Write-Host ('Respaldo de archivos existentes: ' + $destinoBase)
    & git restore --source=FETCH_HEAD --worktree -- $rutas
    if ($LASTEXITCODE -ne 0) {
        throw 'Git no pudo actualizar todos los archivos. Se conservaron los respaldos.'
    }

    Write-Host 'Cara operativa sincronizada. Comprobando sintaxis PHP...' -ForegroundColor Green
    $archivosPhp = @(
        'database/migrations/2026_10_23_000100_crear_acontecimientos_operativos.php',
        'database/migrations/2026_10_23_000200_crear_auditoria_operativa.php',
        'database/migrations/2026_10_24_000100_crear_control_publicaciones_gemini.php',
        'database/migrations/2026_10_24_000200_crear_control_interpretacion_ia.php',
        'app/Services/Operacion/VincularEvidenciasOperativas.php',
        'app/Services/Operacion/RegistrarAcontecimientoOperativo.php',
        'app/Services/Operacion/RevisarAcontecimientoOperativo.php',
        'app/Http/Controllers/Operacion/ControladorOperacion.php',
        'app/Http/Middleware/AutorizarGestionOperacion.php',
        'app/Console/Commands/ProyectarOperacion.php',
        'app/Services/IA/ProcesarAnalisisGemini.php',
        'app/Services/Mensajes/PrepararEventosPanorama.php',
        'app/Services/IA/ControlInterpretacionGemini.php',
        'app/Http/Middleware/AutorizarGestionIA.php',
        'app/Http/Controllers/IA/ControladorConfiguracionGemini.php',
        'tests/Funcional/ControlGeminiWebTest.php',
        'app/Http/Controllers/Mensajes/ControladorMensajes.php',
        'tests/Funcional/PanoramaEventosMensajesTest.php',
        'app/Console/Commands/AnalizarGemini.php',
        'app/Console/Commands/ComprobarGruposIa.php',
        'app/Console/Commands/EstadoGemini.php',
        'app/Console/Commands/DiagnosticarLoteGemini.php',
        'app/Console/Commands/ConciliarGemini.php',
        'app/Jobs/AnalizarMensajeGeminiTrabajo.php',
        'app/Services/IA/ConciliarPublicacionesGemini.php',
        'app/Services/IA/PoliticaEnvioGemini.php',
        'routes/console.php',
        'tests/Funcional/ConciliacionGeminiTest.php',
        'app/Services/IA/ConstruirDialogoGemini.php',
        'app/Services/IA/ClienteGemini.php',
        'app/Services/Normalizacion/NormalizarEventoWhatsapp.php',
        'routes/web.php', 'bootstrap/app.php', 'config/mensajero.php'
    )

    foreach ($archivo in $archivosPhp) {
        & php -l $archivo
        if ($LASTEXITCODE -ne 0) {
            throw ('Error de sintaxis PHP en ' + $archivo)
        }
    }

    Write-Host 'Comprobando sintaxis PowerShell...' -ForegroundColor Green
    $archivosPowerShell = @(
        'scripts/actualizar-cara-operativa.ps1',
        'scripts/activar-conciliacion-gemini.ps1',
        'scripts/autorizar-lote-real-gemini.ps1',
        'scripts/diagnosticar-trabajador-gemini.ps1',
        'scripts/reiniciar-solo-gemini.ps1',
        'scripts/autorizar-grupos-reales-gemini.ps1',
        'scripts/mensajero-local.ps1'
    )
    foreach ($archivo in $archivosPowerShell) {
        $tokens = $null
        $errores = $null
        [void][System.Management.Automation.Language.Parser]::ParseFile(
            (Join-Path $raiz $archivo), [ref] $tokens, [ref] $errores)
        if ($errores.Count -gt 0) {
            throw ('Error de sintaxis PowerShell en ' + $archivo + ': ' + $errores[0].Message)
        }
        Write-Host ('Sintaxis correcta: ' + $archivo)
    }

    if (Get-Command node -ErrorAction SilentlyContinue) {
        & node --check 'public/js/panorama.js'
        if ($LASTEXITCODE -ne 0) {
            throw 'Error de sintaxis JavaScript en public/js/panorama.js'
        }
        Write-Host 'Sintaxis correcta: public/js/panorama.js'
    }

    Write-Host ''
    Write-Host 'Archivos actualizados. NO se aplicaron migraciones a SQL Server.' -ForegroundColor Green
    Write-Host 'Revisar docs/cara-operativa.md y validar el destino SQL antes de migrar.'
    Write-Host 'Los trabajadores Laravel se deben reiniciar luego de migrar.'
} finally {
    Pop-Location
}
