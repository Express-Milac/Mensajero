<?php

/**
 * Ensayo local de Redis bajo una cola generada exclusivamente para PHP 7.4.
 * Uso: php ensayar-cola-redis-php74.php preparar|verificar
 *
 * PREPARAR: publica exactamente un trabajo inofensivo.
 * VERIFICAR: comprueba su acuse y elimina solo las claves de este ensayo.
 * En ningún caso se conecta a SQL Server, WhatsApp o Gemini.
 */

$accion = $argv[1] ?? '';
$id = getenv('MENSAJERO_REDIS74_ID');
$cola = getenv('REDIS_QUEUE');
$prefijo = getenv('REDIS_PREFIX');

if (! in_array($accion, ['preparar', 'verificar'], true)
    || PHP_VERSION_ID !== 70413
    || ! is_string($id)
    || preg_match('/\A[a-f0-9]{32}\z/D', $id) !== 1
    || $cola !== 'mensajero_ensayo_php74_'.$id
    || $prefijo !== 'mensajero:prueba_php74:'.$id.':'
    || getenv('MENSAJERO_REDIS74_ENSAYO') !== 'autorizado_local'
    || getenv('APP_ENV') !== 'testing'
    || getenv('DB_CONNECTION') !== 'sqlite'
    || getenv('DB_DATABASE') !== ':memory:'
    || getenv('QUEUE_CONNECTION') !== 'redis'
    || getenv('REDIS_QUEUE_CONNECTION') !== 'default'
    || getenv('REDIS_CLIENT') !== 'predis'
    || getenv('REDIS_HOST') !== '127.0.0.1'
    || getenv('REDIS_PORT') !== '6379'
    || getenv('REDIS_DB') !== '0'
    || ! in_array(getenv('REDIS_PASSWORD'), ['', false], true)
    || getenv('IA_HABILITADA') !== 'false'
    || getenv('MENSAJERO_IA_ENVIO_AUTORIZADO') !== 'false'
    || getenv('MENSAJERO_IA_LOTES_HABILITADOS') !== 'false') {
    fwrite(STDERR, "BLOQUEADO: parametros fuera del ensayo de cola Redis local PHP74.\n");
    exit(1);
}

$raiz = dirname(__DIR__);
if (! is_file($raiz.'/vendor/autoload.php')
    || is_file($raiz.'/bootstrap/cache/config.php')) {
    fwrite(STDERR, "BLOQUEADO: autoload ausente o configuracion Laravel en cache.\n");
    exit(1);
}

try {
    require $raiz.'/vendor/autoload.php';
    $app = require $raiz.'/bootstrap/app.php';
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

    if (! class_exists(\Tests\Soporte\TrabajoColaRedisPhp74::class)
        || ! $app->environment('testing')
        || config('queue.default') !== 'redis'
        || config('queue.connections.redis.connection') !== 'default'
        || config('database.default') !== 'sqlite'
        || config('database.connections.sqlite.database') !== ':memory:'
        || config('database.redis.client') !== 'predis'
        || config('database.redis.default.host') !== '127.0.0.1'
        || (int) config('database.redis.default.port') !== 6379
        || (int) config('database.redis.default.database') !== 0
        || config('database.redis.options.prefix') !== $prefijo
        || config('queue.failed.driver') !== null) {
        throw new RuntimeException('Configuracion efectiva fuera del ensayo aislado.');
    }

    $redis = \Illuminate\Support\Facades\Redis::connection('default');
    $trabajos = \Illuminate\Support\Facades\Queue::connection('redis');
    $acuse = 'acuse:'.$id;
    $clavePendiente = 'queues:'.$cola;
    $claveNotificacion = 'queues:'.$cola.':notify';
    $claveReservada = 'queues:'.$cola.':reserved';
    $claveAplazada = 'queues:'.$cola.':delayed';

    if ($accion === 'preparar') {
        // No permitir una segunda publicación con el mismo identificador.
        foreach ([$acuse, $clavePendiente, $claveNotificacion, $claveReservada, $claveAplazada] as $clave) {
            if ((int) $redis->exists($clave) !== 0) {
                throw new RuntimeException('La cola exclusiva ya existe: no publicar duplicados.');
            }
        }

        $trabajos->pushOn($cola, new \Tests\Soporte\TrabajoColaRedisPhp74($id));

        // Claves EXCLUSIVAS al ensayo. Su vencimiento limita los residuos
        // en Redis si PowerShell se cierra antes de verificar el resultado.
        $redis->expire($clavePendiente, 1200);
        $redis->expire($claveNotificacion, 1200);
        if ((int) $trabajos->size($cola) !== 1) {
            throw new RuntimeException('Redis no contiene exactamente un trabajo.');
        }

        echo "COLA_EXCLUSIVA_VERIFICADA: ".$cola.PHP_EOL;
        echo "TRABAJOS_PUBLICADOS: 1\n";
        echo "PREPARACION_COLA_REDIS74_APROBADA\n";
    } else {
        $valor = $redis->get($acuse);
        if ($valor !== 'ejecutado'
            || (int) $trabajos->size($cola) !== 0) {
            throw new RuntimeException('El trabajador no dejo el acuse esperado o la cola no esta vacia.');
        }

        echo "COLA_EXCLUSIVA_VERIFICADA: ".$cola.PHP_EOL;
        echo "TRABAJO_PROCESADO: SI\n";
        echo "COLA_VACIA: SI\n";

        // Limpiar solamente claves generadas con el identificador aleatorio
        // de ESTA ejecucion y el prefijo efimero ya validado.
        foreach ([$acuse, $clavePendiente, $claveNotificacion, $claveReservada, $claveAplazada] as $clave) {
            $redis->del([$clave]);
        }

        echo "CLAVES_DE_ENSAYO_LIMPIADAS: SI\n";
        echo "ENSAYO_COLA_REDIS74_APROBADO\n";
    }
} catch (\Throwable $error) {
    // No exponer errores de transporte, credenciales ni volcados de consola.
    fwrite(STDERR, "ENSAYO_COLA_REDIS74_PENDIENTE: ".get_class($error).PHP_EOL);
    exit(1);
}
