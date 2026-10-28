<?php

/**
 * Diagnostico NO DESTRUCTIVO de Redis para la migracion Mensajero PHP 7.4.
 *
 * Solo se conecta al Redis local 127.0.0.1:6379 y ejecuta PING.
 * No lee claves ni publica trabajos ni toca datos SQL Server.
 * El llamador debe fijar variables de entorno de proceso temporales.
 */
if (PHP_VERSION_ID !== 70413
    || getenv('APP_ENV') !== 'testing'
    || getenv('REDIS_CLIENT') !== 'predis'
    || getenv('REDIS_HOST') !== '127.0.0.1'
    || getenv('REDIS_PORT') !== '6379'
    || getenv('REDIS_DB') !== '0'
    || getenv('QUEUE_CONNECTION') !== 'sync'
    || getenv('IA_HABILITADA') !== 'false'
    || getenv('MENSAJERO_IA_ENVIO_AUTORIZADO') !== 'false'
    || getenv('MENSAJERO_IA_LOTES_HABILITADOS') !== 'false'
    || ! in_array(getenv('REDIS_PASSWORD'), ['', false], true)) {
    fwrite(STDERR, "BLOQUEADO: requiere entorno de pruebas y Redis local sin clave.\n");
    exit(1);
}

$raiz = dirname(__DIR__);
if (is_file($raiz.'/bootstrap/cache/config.php')) {
    fwrite(STDERR, "BLOQUEADO: configuracion Laravel en cache.\n");
    exit(1);
}

if (! is_file($raiz.'/vendor/autoload.php')) {
    fwrite(STDERR, "BLOQUEADO: faltan dependencias Composer locales.\n");
    exit(1);
}

try {
    require $raiz.'/vendor/autoload.php';

    if (! class_exists('Predis\\Client')) {
        throw new RuntimeException('No se encuentra Predis en vendor.');
    }

    $app = require $raiz.'/bootstrap/app.php';
    $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();

    if ($app->environment() !== 'testing'
        || config('database.redis.client') !== 'predis'
        || config('database.redis.default.host') !== '127.0.0.1'
        || (int) config('database.redis.default.port') !== 6379
        || (int) config('database.redis.default.database') !== 0
        || config('queue.default') !== 'sync') {
        throw new RuntimeException('La configuracion efectiva no corresponde al ensayo local.');
    }

    $conexion = $app->make('redis')->connection('default');
    $respuesta = $conexion->ping();

    // Predis 1.x puede devolver objeto Status en lugar de cadena.
    $textoRespuesta = is_object($respuesta) && method_exists($respuesta, '__toString')
        ? (string) $respuesta : (is_string($respuesta) ? $respuesta : '');
    if ($respuesta !== true && strtoupper(ltrim($textoRespuesta, '+')) !== 'PONG') {
        throw new RuntimeException('Redis no respondio PONG.');
    }

    echo 'PHP_VERIFICADO: '.PHP_VERSION.PHP_EOL;
    echo "CLIENTE_REDIS: predis\n";
    echo "REDIS_LOCAL_VERIFICADO: 127.0.0.1:6379\n";
    echo "QUEUE_CONNECTION: sync (sin trabajadores)\n";
    echo "REDIS_PING: PONG\n";
    echo "CLAVES_CONSULTADAS: 0\n";
    echo "TRABAJOS_PUBLICADOS: 0\n";
    echo "DIAGNOSTICO_REDIS74_SOLO_LECTURA_APROBADO\n";
} catch (Throwable $error) {
    // Nunca imprimir excepciones completas: pueden contener datos de conexion.
    fwrite(STDERR, 'DIAGNOSTICO_REDIS74_PENDIENTE: '.get_class($error).PHP_EOL);
    exit(1);
}
