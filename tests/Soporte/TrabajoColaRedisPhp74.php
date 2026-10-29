<?php

namespace Tests\Soporte;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Redis;
use RuntimeException;

/**
 * Trabajo de prueba exclusivamente local; no utiliza SQL, WhatsApp ni IA.
 * Se encuentra en tests/ (autoload-dev), fuera del código operativo.
 */
final class TrabajoColaRedisPhp74 implements ShouldQueue
{
    use Queueable;

    public $tries = 1;
    public $timeout = 15;

    private $identificador;

    public function __construct(string $identificador)
    {
        if (preg_match('/\A[a-f0-9]{32}\z/D', $identificador) !== 1) {
            throw new RuntimeException('Identificador de ensayo inválido.');
        }

        $this->identificador = $identificador;
    }

    public function handle(): void
    {
        $identificador = $this->identificador;

        if (PHP_VERSION_ID !== 70413
            || ! app()->environment('testing')
            || config('queue.default') !== 'redis'
            || config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:'
            || config('database.redis.options.prefix') !== 'mensajero:prueba_php74:'.$identificador.':'
            || getenv('MENSAJERO_REDIS74_ID') !== $identificador
            || config('queue.connections.redis.connection') !== 'default'
            || getenv('IA_HABILITADA') !== 'false'
            || getenv('MENSAJERO_IA_ENVIO_AUTORIZADO') !== 'false') {
            throw new RuntimeException('Trabajo bloqueado fuera del ensayo local.');
        }

        // Esta es la UNICA escritura del trabajo: acuse con caducidad.
        Redis::connection('default')->setex('acuse:'.$identificador, 600, 'ejecutado');
    }
}
