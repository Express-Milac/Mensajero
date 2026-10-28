<?php

namespace App\Console\Commands;

use App\Jobs\AnalizarLoteGeminiTrabajo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Ensayo de transporte concurrente con DOS procesos PHP independientes.
 * Dos trabajos inofensivos de lote 0, cola Redis exclusiva, Gemini apagado.
 * No verifica todavia la idempotencia de dos workers sobre un lote real.
 */
final class EnsayarDosTrabajadoresLotesRedis extends Command
{
    protected $signature = 'mensajero:ensayar-dos-trabajadores-lotes-redis
        {--aplicar : Publicar dos trabajos ficticios y consumir uno con cada trabajador aislado}';

    protected $description = 'Prueba dos trabajadores Redis independientes sin solicitudes a Gemini ni lotes reales';

    public function handle(): int
    {
        if (! app()->environment('testing')
            || config('database.default') !== 'sqlsrv'
            || config('database.connections.sqlsrv.database') !== 'mensajero_integracion_test'
            || config('mensajero.ia.habilitada') !== false
            || config('mensajero.ia.envio_autorizado') !== false
            || config('mensajero.ia.lotes_habilitados') !== false) {
            $this->error('Bloqueado: requiere entorno testing, SQL Server aislado y Gemini apagado.');

            return self::FAILURE;
        }

        try {
            if (DB::selectOne('SELECT DB_NAME() AS nombre')->nombre !== 'mensajero_integracion_test') {
                $this->error('La base SQL conectada no corresponde a integracion.');

                return self::FAILURE;
            }
        } catch (Throwable) {
            $this->error('No se pudo comprobar la identidad de la base SQL Server.');

            return self::FAILURE;
        }

        if (! $this->option('aplicar')) {
            $this->components->info('Vista previa: dos trabajos de lote 0 en una cola Redis nueva y aleatoria.');
            $this->line('Arrancan dos procesos PHP independientes, cada uno con queue:work --once.');
            $this->line('Gemini permanece apagado. No se usan colas operativas ni mensajes reales.');
            $this->line('Este ensayo solo verifica entrega concurrente, NO la idempotencia sobre un lote real.');

            return self::SUCCESS;
        }

        $nombreCola = 'mensajero_dos_trabajadores_ensayo_'.bin2hex(random_bytes(12));
        $cola = Queue::connection('redis');
        $procesos = [];
        $publicados = 0;
        $aprobado = false;

        try {
            if ($cola->size($nombreCola) !== 0) {
                throw new \RuntimeException('La cola de ensayo aleatoria no estaba vacia.');
            }

            for ($i = 0; $i < 2; $i++) {
                $trabajo = (new AnalizarLoteGeminiTrabajo(0))->onQueue($nombreCola);
                $trabajo->timeout = 10;
                $cola->push($trabajo, '', $nombreCola);
                $publicados++;
            }

            $entorno = [
                'APP_ENV' => 'testing',
                'DB_CONNECTION' => 'sqlsrv',
                'DB_DATABASE' => 'mensajero_integracion_test',
                'DB_URL' => '',
                'IA_HABILITADA' => 'false',
                'MENSAJERO_IA_ENVIO_AUTORIZADO' => 'false',
                'MENSAJERO_IA_SERVICIO_PAGADO_CONFIRMADO' => 'false',
                'MENSAJERO_IA_LOTES_HABILITADOS' => 'false',
                'MENSAJERO_NORMALIZACION_HABILITADA' => 'false',
            ];

            $comando = [
                PHP_BINARY, base_path('artisan'), 'queue:work', 'redis',
                '--queue='.$nombreCola, '--once', '--tries=1',
                '--timeout=10', '--sleep=0', '--verbose', '--no-ansi',
            ];

            for ($i = 0; $i < 2; $i++) {
                $proceso = new Process($comando, base_path(), $entorno);
                $proceso->setTimeout(20);
                $proceso->start();
                $procesos[] = $proceso;
            }

            foreach ($procesos as $indice => $proceso) {
                $proceso->wait();
                $salida = $proceso->getOutput();
                $error = $proceso->getErrorOutput();
                $exito = $proceso->getExitCode() === 0
                    && str_contains($salida, 'DONE')
                    && ! str_contains($salida, 'FAIL')
                    && trim($error) === '';
                $this->line(sprintf('Trabajador %d: %s.', $indice + 1, $exito ? 'termino el trabajo' : 'sin confirmacion'));
                if (! $exito) {
                    throw new \RuntimeException('Uno de los trabajadores no confirmó la ejecucion correcta.');
                }
            }

            if ($cola->size($nombreCola) !== 0) {
                throw new \RuntimeException('La cola no quedo vacia al finalizar ambos trabajadores.');
            }

            $aprobado = true;
            $this->components->info('Dos trabajadores Redis aprobados: dos trabajos ficticios, cero pendientes.');
            $this->line('Cola exclusiva: '.$nombreCola);
            $this->line('Sin Gemini, SQL escrito ni trabajos operativos ejecutados.');
            $this->warn('Esta prueba no demuestra aun que dos trabajadores eviten procesar un mismo lote real.');

            return self::SUCCESS;
        } catch (Throwable $error) {
            $this->error('Ensayo concurrente no aprobado: '.class_basename($error));
            $this->line('Cola aleatoria para diagnostico: '.$nombreCola);

            return self::FAILURE;
        } finally {
            foreach ($procesos as $proceso) {
                if ($proceso->isRunning()) {
                    $proceso->stop(1);
                }
            }

            if (! $aprobado && $publicados > 0) {
                try {
                    for ($i = 0; $i < $publicados; $i++) {
                        $pendiente = $cola->pop($nombreCola);
                        if ($pendiente === null) {
                            break;
                        }
                        $pendiente->delete();
                    }
                } catch (Throwable $error) {
                    $this->warn('No se pudo retirar todo el ensayo; revisar SOLO la cola indicada.');
                }
            }
        }
    }
}
