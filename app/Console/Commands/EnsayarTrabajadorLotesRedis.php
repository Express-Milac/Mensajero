<?php

namespace App\Console\Commands;

use App\Jobs\AnalizarLoteGeminiTrabajo;
use Illuminate\Console\Command;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Throwable;

/**
 * Ejecuta exactamente un trabajo ficticio en un trabajador Laravel/Redis.
 * La cola es nueva y aleatoria; no se usa ninguna cola operativa.
 */
final class EnsayarTrabajadorLotesRedis extends Command
{
    protected $signature = 'mensajero:ensayar-trabajador-lotes-redis
        {--aplicar : Publicar un trabajo ficticio en una cola unica y consumirlo con --once}';

    protected $description = 'Comprueba el trabajador Redis de lotes sin ejecutar Gemini ni usar datos reales';

    public function handle(): int
    {
        if (! app()->environment('testing')
            || config('database.default') !== 'sqlsrv'
            || config('database.connections.sqlsrv.database') !== 'mensajero_integracion_test'
            || config('mensajero.ia.habilitada') !== false
            || config('mensajero.ia.envio_autorizado') !== false
            || config('mensajero.ia.lotes_habilitados') !== false) {
            $this->error('Bloqueado: requiere testing, SQL Server de integracion y Gemini apagado.');

            return self::FAILURE;
        }

        try {
            if (DB::selectOne('SELECT DB_NAME() AS nombre')->nombre !== 'mensajero_integracion_test') {
                $this->error('Bloqueado: la conexion SQL real no apunta a la base de integracion.');

                return self::FAILURE;
            }
        } catch (Throwable) {
            $this->error('No fue posible comprobar la base SQL Server.');

            return self::FAILURE;
        }

        if (! $this->option('aplicar')) {
            $this->components->info('Vista previa: se creara una cola Redis nueva de nombre aleatorio.');
            $this->line('Se publicara solo AnalizarLoteGeminiTrabajo(0), que no puede contactar Gemini.');
            $this->line('Un trabajador --once procesara SOLO esa cola, nunca las colas operativas.');
            $this->line('La ejecucion comprobara evento de exito, ausencia de fallos y cola vacia.');

            return self::SUCCESS;
        }

        $nombreCola = 'mensajero_trabajador_lotes_ensayo_'.bin2hex(random_bytes(12));
        $procesados = 0;
        $fallos = 0;
        $publicado = false;
        $cola = Queue::connection('redis');

        Http::preventStrayRequests();

        Queue::after(function (JobProcessed $evento) use ($nombreCola, &$procesados): void {
            if ($evento->connectionName === 'redis'
                && $evento->job->getQueue() === $nombreCola
                && ($evento->job->payload()['data']['commandName'] ?? null) === AnalizarLoteGeminiTrabajo::class) {
                $procesados++;
            }
        });
        Queue::failing(function (JobFailed $evento) use ($nombreCola, &$fallos): void {
            if ($evento->connectionName === 'redis' && $evento->job->getQueue() === $nombreCola) {
                $fallos++;
            }
        });

        try {
            $trabajo = (new AnalizarLoteGeminiTrabajo(0))->onQueue($nombreCola);
            $trabajo->timeout = 10;
            $cola->push($trabajo, '', $nombreCola);
            $publicado = true;

            // Es el trabajador real de Laravel, pero solo durante una ejecucion.
            $salida = Artisan::call('queue:work', [
                'connection' => 'redis',
                '--queue' => $nombreCola,
                '--once' => true,
                '--tries' => 1,
                '--timeout' => 10,
                '--sleep' => 0,
            ]);

            $restantes = $cola->size($nombreCola);
            Http::assertNothingSent();

            if ($salida !== self::SUCCESS || $procesados !== 1 || $fallos !== 0 || $restantes !== 0) {
                $this->error(sprintf(
                    'Ensayo no aprobado (salida=%d, procesados=%d, fallos=%d, pendientes=%d).',
                    $salida, $procesados, $fallos, $restantes
                ));
                $this->line('Cola de diagnostico: '.$nombreCola);

                return self::FAILURE;
            }

            $this->components->info('Trabajador Redis aprobado: un trabajo procesado, cero fallos.');
            $this->line('Cola exclusiva: '.$nombreCola);
            $this->line('Lote ficticio: 0; cola vacia y sin solicitudes HTTP.');
            $this->line('No se ejecutaron colas operativas, Google ni trabajos con mensajes reales.');

            return self::SUCCESS;
        } catch (Throwable $error) {
            $this->error('Ensayo de trabajador fallido: '.class_basename($error));
            $this->line('Cola exclusiva para diagnostico: '.$nombreCola);

            return self::FAILURE;
        } finally {
            // Si el trabajador ni siquiera consumio el trabajo, intentar retirarlo.
            // Nunca operar sobre nombres de colas ajenos a esta invocacion.
            if ($publicado && $procesados === 0) {
                try {
                    $pendiente = $cola->pop($nombreCola);
                    if ($pendiente !== null) {
                        $pendiente->delete();
                    }
                } catch (Throwable $error) {
                    $this->warn('La limpieza de la cola ficticia requiere revision: '.class_basename($error));
                }
            }
        }
    }
}
