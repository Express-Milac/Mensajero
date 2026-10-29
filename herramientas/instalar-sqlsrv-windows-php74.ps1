# Instalacion privada, reversible y aislada de SQLSRV 5.10.1 para PHP 7.4.13 NTS x64.
# No toca PHP de Windows, registro, ODBC, servicios, BD ni archivos de MILAC.
# Fuente oficial Microsoft:
# https://learn.microsoft.com/en-us/sql/connect/php/release-notes-php-sql-driver
# ZIP Microsoft oficial v5.10.1:
# https://download.microsoft.com/download/f/4/d/f4d95d48-74ae-4d72-a602-02145a5f29c8/5.10.1/SQLSRV510.ZIP
$ErrorActionPreference = "Stop"

$base = "C:\Users\6MGHN64_\Documents\Desarrollo Milac"
$phpDir = Join-Path $base "Herramientas-Mensajero-PHP74\PHP-7.4.13"
$php = Join-Path $phpDir "php.exe"
$ini = Join-Path $phpDir "php.ini"
$ext = Join-Path $phpDir "ext"
$descargas = Join-Path $base "Herramientas-Mensajero-PHP74\Paquetes"
$archivo = Join-Path $descargas "SQLSRV510.ZIP"
$extraidos = Join-Path $descargas "SQLSRV510-extraido"
$url = "https://download.microsoft.com/download/f/4/d/f4d95d48-74ae-4d72-a602-02145a5f29c8/5.10.1/SQLSRV510.ZIP"

if (-not (Test-Path -LiteralPath $php -PathType Leaf) -or
    -not (Test-Path -LiteralPath $ini -PathType Leaf) -or
    -not (Test-Path -LiteralPath $ext -PathType Container)) {
    throw "No se encontro la instalacion PHP 7.4 aislada completa."
}

# Expresiones sin comillas internas: seguras con PowerShell 5.1 y php -r.
$version = & $php -r 'echo PHP_VERSION_ID;'
if ($LASTEXITCODE -ne 0) { throw "No fue posible obtener PHP_VERSION_ID." }
$bits = & $php -r 'echo PHP_INT_SIZE;'
if ($LASTEXITCODE -ne 0) { throw "No fue posible obtener PHP_INT_SIZE." }
$hilos = & $php -r 'echo PHP_ZTS;'
if ($LASTEXITCODE -ne 0) { throw "No fue posible obtener PHP_ZTS." }
if ($version -ne "70413" -or $bits -ne "8" -or $hilos -ne "0") {
    throw "Se requiere PHP 7.4.13, 64 bits, NTS. Observado: version=$version bytes=$bits hilos=$hilos"
}

$odbc = @(Get-OdbcDriver -Platform "64-bit" |
    Where-Object { $_.Name -eq "ODBC Driver 18 for SQL Server" })
if ($odbc.Count -lt 1) {
    throw "Falta ODBC Driver 18 de 64 bits. No se modifica Windows."
}

$nombres = @("php_pdo_sqlsrv_74_nts_x64.dll", "php_sqlsrv_74_nts_x64.dll")
foreach ($nombre in $nombres) {
    if (Test-Path -LiteralPath (Join-Path $ext $nombre)) {
        throw "El controlador $nombre ya existe; no se sobrescribira. Revisar manualmente."
    }
}

New-Item -ItemType Directory -Path $descargas -Force | Out-Null
if (Test-Path -LiteralPath $archivo) {
    throw "Ya existe $archivo. No reutilizar ni sobrescribir un ZIP sin verificar."
}
if (Test-Path -LiteralPath $extraidos) {
    throw "Ya existe $extraidos. No reutilizar una extraccion anterior."
}

# Habilitar TLS 1.2 solamente durante esta descarga del proceso de PowerShell.
$protocoloAnterior = [Net.ServicePointManager]::SecurityProtocol
try {
    [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
    Write-Host "=== DESCARGANDO DRIVER OFICIAL MICROSOFT 5.10.1 ==="
    Invoke-WebRequest -Uri $url -OutFile $archivo -UseBasicParsing
} finally {
    [Net.ServicePointManager]::SecurityProtocol = $protocoloAnterior
}

$hash = (Get-FileHash -LiteralPath $archivo -Algorithm SHA256).Hash
Write-Host "ZIP SHA256 (auditoria): $hash"

Expand-Archive -LiteralPath $archivo -DestinationPath $extraidos -ErrorAction Stop

$seleccion = @{}
foreach ($nombre in $nombres) {
    $candidatos = @(Get-ChildItem -LiteralPath $extraidos -Filter $nombre -File -Recurse)
    if ($candidatos.Count -ne 1) {
        throw "Se esperaba exactamente una DLL x64 NTS $nombre; encontradas: $($candidatos.Count). No se modifica PHP."
    }
    $seleccion[$nombre] = $candidatos[0].FullName
}

$contenidoIni = Get-Content -LiteralPath $ini -Raw
foreach ($nombre in $nombres) {
    $patron = '(?m)^\s*extension\s*=\s*"?'+[regex]::Escape($nombre)+'"?\s*$'
    if ([regex]::IsMatch($contenidoIni, $patron)) {
        throw "php.ini ya contiene una extension activa $nombre. No se duplicara."
    }
}

$fecha = Get-Date -Format "yyyyMMdd-HHmmss"
$backup = "$ini.respaldo-$fecha"
Copy-Item -LiteralPath $ini -Destination $backup -ErrorAction Stop

$instaladas = @()
try {
    foreach ($nombre in $nombres) {
        Copy-Item -LiteralPath $seleccion[$nombre] -Destination (Join-Path $ext $nombre) -ErrorAction Stop
        $instaladas += (Join-Path $ext $nombre)
    }

    Add-Content -LiteralPath $ini -Encoding Ascii -Value "`r`n; SQL Server 5.10.1 - PHP 7.4.13 NTS x64 - entorno de pruebas`r`nextension=php_pdo_sqlsrv_74_nts_x64.dll`r`nextension=php_sqlsrv_74_nts_x64.dll"

    Write-Host "=== VALIDACION DE CARGA (SIN CONSULTAR SQL SERVER) ==="
    $modulos = @(& $php -m 2>&1 | ForEach-Object { [string] $_ })
    if ($LASTEXITCODE -ne 0 -or
        $modulos -notcontains "pdo_sqlsrv" -or
        $modulos -notcontains "sqlsrv") {
        throw "PHP 7.4 no cargo ambas extensiones. Salida: $($modulos -join '; ')"
    }

    Write-Host "PDO_SQLSRV: OK"
    Write-Host "SQLSRV: OK"
    Write-Host "PHP 7.4.13: OK"
    Write-Host "Respaldo de php.ini: $backup"
    Write-Host "=== CONTROLADORES DE SQL SERVER INSTALADOS EN PHP PORTATIL ==="
} catch {
    Copy-Item -LiteralPath $backup -Destination $ini -Force
    foreach ($ruta in $instaladas) {
        if (Test-Path -LiteralPath $ruta) { Remove-Item -LiteralPath $ruta -Force }
    }
    throw
}
