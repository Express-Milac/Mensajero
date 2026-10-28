<?php

/**
 * Diagnóstico estrictamente de solo lectura del PHP 7.4 y SQL Server de test.
 * No ejecuta migraciones, no escribe datos ni inicia colas o envíos HTTP.
 */
$base = dirname(__DIR__);

if (PHP_VERSION_ID !== 70413) {
    fwrite(STDERR, 'BLOQUEADO: este diagnóstico exige PHP 7.4.13 real.'.PHP_EOL);
    exit(1);
}

if (! extension_loaded('pdo_sqlsrv') || ! in_array('sqlsrv', PDO::getAvailableDrivers(), true)) {
    fwrite(STDERR, 'BLOQUEADO: PHP 7.4.13 no tiene cargado PDO_SQLSRV.'.PHP_EOL);
    exit(1);
}

if (getenv('APP_ENV') !== 'testing'
    || getenv('DB_CONNECTION') !== 'sqlsrv'
    || getenv('DB_DATABASE') !== 'mensajero_integracion_test'
    || ! in_array(getenv('DB_URL'), ['', false], true)
    || getenv('IA_HABILITADA') !== 'false'
    || getenv('MENSAJERO_IA_ENVIO_AUTORIZADO') !== 'false'
    || getenv('MENSAJERO_IA_LOTES_HABILITADOS') !== 'false'
    || getenv('QUEUE_CONNECTION') !== 'sync') {
    fwrite(STDERR, 'BLOQUEADO: variables del proceso no corresponden al ensayo aislado.'.PHP_EOL);
    exit(1);
}

if (is_file($base.'/bootstrap/cache/config.php')) {
    fwrite(STDERR, 'BLOQUEADO: hay configuracion Laravel en cache.'.PHP_EOL);
    exit(1);
}

if (! is_file($base.'/vendor/autoload.php')) {
    fwrite(STDERR, 'BLOQUEADO: Composer vendor no existe en este clon.'.PHP_EOL);
    exit(1);
}

try {
    require $base.'/vendor/autoload.php';
    $app = require $base.'/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

    if (! $app->environment('testing')
        || config('database.default') !== 'sqlsrv'
        || config('database.connections.sqlsrv.database') !== 'mensajero_integracion_test'
        || config('mensajero.ia.habilitada') !== false
        || config('mensajero.ia.envio_autorizado') !== false
        || config('mensajero.ia.lotes_habilitados') !== false) {
        throw new RuntimeException('La configuracion efectiva no es segura.');
    }

    $conexion = Illuminate\Support\Facades\DB::connection('sqlsrv');
    if ($conexion->getDriverName() !== 'sqlsrv') {
        throw new RuntimeException('El controlador de SQL Server es incorrecto.');
    }

    // ÚNICAS operaciones SQL: dos SELECT de solo lectura.
    $fisica = $conexion->selectOne('SELECT DB_NAME() AS nombre');
    if (! isset($fisica->nombre) || $fisica->nombre !== 'mensajero_integracion_test') {
        throw new RuntimeException('La base SQL FISICA no es la base de integración.');
    }

    $tablas = $conexion->selectOne(
        "SELECT COUNT(*) AS cantidad FROM sys.tables WHERE name LIKE 'mensajero[_]%'"
    );
    echo 'PHP_VERIFICADO: '.PHP_VERSION.PHP_EOL;
    echo 'BASE_SQL_FISICA_VERIFICADA: mensajero_integracion_test'.PHP_EOL;
    echo 'TABLAS_MENSAJERO_EN_BASE_TEST: '.(int) $tablas->cantidad.PHP_EOL;
    echo 'DIAGNOSTICO_SQL74_SOLO_LECTURA_APROBADO'.PHP_EOL;
} catch (Throwable $error) {
    // Nunca imprimir SQL, usuario, contrasena ni datos del servidor.
    fwrite(STDERR, 'BLOQUEADO: fallo de conexion o configuracion ('.
        get_class($error).'). Revisar el entorno SIN publicar secretos.'.PHP_EOL);
    exit(1);
}
