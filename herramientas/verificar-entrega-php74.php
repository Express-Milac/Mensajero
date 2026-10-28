<?php

/**
 * Verificador previo a la ENTREGA del codigo de Mensajero para MILAC.
 *
 * SOLO LECTURA y sin cargar Laravel: no conecta SQL Server, Redis,
 * WhatsApp, ni hace llamadas de red. No lee .env ni secretos.
 * Requiere PHP CLI 7.4.13 como interprete de la prueba.
 *
 * Una aprobacion significa "paquete fuente local coherente para entrega",
 * NO autoriza instalacion ni migraciones en la base corporativa.
 */

$raiz = dirname(__DIR__);
$errores = [];
$advertencias = [];

function mostrarRevision($descripcion, $correcto)
{
    echo ($correcto ? 'OK' : 'FALLO').': '.$descripcion.PHP_EOL;
}

function exigir($descripcion, $correcto, array &$errores)
{
    mostrarRevision($descripcion, $correcto);
    if (! $correcto) {
        $errores[] = $descripcion;
    }
}

function leerJson($archivo)
{
    if (! is_file($archivo) || ! is_readable($archivo)) {
        return null;
    }

    $valor = json_decode((string) file_get_contents($archivo), true);

    return is_array($valor) ? $valor : null;
}

echo "=== REVISION DE ENTREGA MENSAJERO MILAC / PHP 7.4 ===\n";
echo "Operaciones permitidas: lectura de archivos y version/extensiones PHP.\n";

exigir('PHP CLI 7.4.13 exacto', PHP_VERSION_ID === 70413, $errores);

foreach (['PDO', 'pdo_sqlsrv', 'sqlsrv', 'mbstring', 'curl',
          'openssl', 'xml', 'zip', 'intl', 'fileinfo',
          'bcmath', 'ctype', 'json'] as $extension) {
    exigir('Extension PHP '.$extension, extension_loaded($extension), $errores);
}

$composer = leerJson($raiz.'/composer.json');
$lock = leerJson($raiz.'/composer.lock');

exigir('composer.json legible y valido', $composer !== null, $errores);
exigir('composer.lock legible y valido', $lock !== null, $errores);

if ($composer !== null && $lock !== null) {
    exigir(
        'Composer exige plataforma PHP 7.4.13',
        isset($composer['config']['platform']['php'])
            && $composer['config']['platform']['php'] === '7.4.13',
        $errores
    );

    exigir(
        'Lock generado para plataforma PHP 7.4.13',
        isset($lock['platform-overrides']['php'])
            && $lock['platform-overrides']['php'] === '7.4.13',
        $errores
    );

    $versionLaravel = null;
    foreach (($lock['packages'] ?? []) as $paquete) {
        if (($paquete['name'] ?? '') === 'laravel/framework') {
            $versionLaravel = $paquete['version'] ?? null;
            break;
        }
    }

    exigir(
        'Laravel 8.83.x fijado en composer.lock',
        is_string($versionLaravel)
            && preg_match('/^v?8\.83\.\d+$/D', $versionLaravel) === 1,
        $errores
    );
}

$obligatorios = [
    'bootstrap/app.php',
    'artisan',
    'public/index.php',
    'public/.htaccess',
    'public/css/mensajero.css',
    'public/js/panorama.js',
    'config/database.php',
    'config/queue.php',
    'config/mensajero.php',
    'database/migrations/2026_10_15_000100_crear_nucleo_captura_mensajero.php',
    'recolector-whatsapp/package.json',
    'recolector-whatsapp/.env.example',
    'docs/ENTREGA-INSTALADOR-MILAC-PHP74.md',
    'docs/avance-validacion-php74-20261028.md',
];

foreach ($obligatorios as $archivo) {
    exigir('Archivo fuente '.$archivo, is_file($raiz.'/'.$archivo), $errores);
}

$plantilla = is_file($raiz.'/.env.example')
    ? (string) file_get_contents($raiz.'/.env.example') : '';

foreach ([
    'APP_ENV=production',
    'APP_DEBUG=false',
    'MENSAJERO_MODO_AUTENTICACION=corporativo',
    'MENSAJERO_CONSULTA_LOCAL_SIN_AUTENTICACION=false',
    'SESSION_SECURE_COOKIE=true',
    'DB_ENCRYPT=yes',
    'DB_TRUST_SERVER_CERTIFICATE=false',
    'IA_HABILITADA=false',
    'MENSAJERO_IA_ENVIO_AUTORIZADO=false',
    'MENSAJERO_IA_LOTES_HABILITADOS=false',
    'MENSAJERO_NORMALIZACION_HABILITADA=false',
] as $valor) {
    exigir('Plantilla segura: '.$valor, strpos($plantilla, $valor."\n") !== false
        || substr($plantilla, -strlen($valor)) === $valor, $errores);
}

$gitignore = is_file($raiz.'/.gitignore')
    ? (string) file_get_contents($raiz.'/.gitignore') : '';
foreach (['.env', '/vendor', '/node_modules', '/recolector-whatsapp/datos/'] as $entrada) {
    exigir('Exclusion Git: '.$entrada, strpos($gitignore, $entrada) !== false, $errores);
}

if (is_file($raiz.'/.env')) {
    $advertencias[] = 'Hay .env local: NO transferirlo como parte del paquete de entrega.';
}
if (is_dir($raiz.'/vendor')) {
    $advertencias[] = 'vendor local: reconstruir dependencias en Linux; NO copiar vendor de Windows.';
}
if (is_dir($raiz.'/recolector-whatsapp/node_modules')) {
    $advertencias[] = 'node_modules local: reconstruir por separado para Linux.';
}

foreach ($advertencias as $advertencia) {
    echo 'AVISO: '.$advertencia.PHP_EOL;
}

if (count($errores) !== 0) {
    echo 'ENTREGA_PHP74_PENDIENTE: '.count($errores).' requisito(s) fallidos.'.PHP_EOL;
    exit(1);
}

echo "ENTREGA_FUENTES_PHP74_VERIFICADA\n";
echo "REQUIERE_INSTALADOR_Y_VALIDACION_EN_MILAC: SI\n";
echo "NO_AUTORIZA_MIGRACIONES_NI_PUBLICACION: SI\n";
