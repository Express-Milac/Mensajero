<?php

namespace App\Console\Commands;

use App\Jobs\AnalizarLoteGeminiTrabajo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Throwable;

/**
 * Verifica el transporte real de Redis sin ejecutar un trabajador.
 * Publica y retira un trabajo inofensivo (loteId=0) en una cola aleatoria.
 */
final class VerificarTransporteLotesRedis extends Command
{
    protected $signature = 'mensajero:verificar-transporte-lotes-redis
        {--aplicar : Publicar y retirar un trabajo ficticio de una cola exclusiva en Redis}';

    protected $description = 'Comprueba la cola Redis para lotes sin iniciar trabajadores ni contactar Gemini';

    public function handle(): int
    {
        // El comando falla cerrado y nunca actua sobre bases operativas.
        if (! app()->environment('testing')
            || config('database.default') !== 'sqlsrv'
            || config('database.connections.sqlsrv.database') !== 'mensajero_integracion_test'
            || config('mensajero.ia.habilitada') !== false
            || config('mensajero.ia.lotes_habilitados') !== false) {
            $this->error('Bloqueado: requiere testing, SQL Server aislado y Gemini apagado.');

            return self::FAILURE;
        }

        try {
            if (DB::selectOne('SELECT DB_NAME() AS nombre')->nombre !== 'mensajero_integracion_test') {
                $this->error('Bloqueado: la base SQL Server real no es la de integracion.');

                return self::FAILURE;
            }
        } catch (Throwable) {
            $this->error('No se pudo verificar la identidad de SQL Server.');

            return self::FAILURE;
        }

        if (! $this->option('aplicar')) {
            $this->components->info('Vista previa: se publicara y retirara un trabajo ficticio con lote ID 0.');
            $this->line('Se usara una cola Redis nueva, aleatoria y exclusiva de este ensayo.');
            $this->line('No se iniciara ningun trabajador, no se modificara SQL y no se consultara Google.');
            $this->line('Para ejecutar: agregue --aplicar.');

            return self::SUCCESS;
        }

        // No reutilizar nunca las colas operativas ni sus trabajos.
        $nombreCola = 'mensajero_lotes_ensayo_'.bin2hex(random_bytes(12));
        $cola = Queue::connection('redis');
        $recibido = null;
        $publicado = false;
        $valido = false;
        $retirado = false;

        try {
            // ID 0 queda descartado antes de consultar permisos, SQL o Google.
            // Aqui solo se transporta el trabajo: no se ejecuta handle().
            $trabajo = (new AnalizarLoteGeminiTrabajo(0))->onQueue($nombreCola);
            $cola->push($trabajo, '', $nombreCola);
            $publicado = true;
            $recibido = $cola->pop($nombreCola);

            if ($recibido === null) {
                $this->error('Redis no devolvio el trabajo sintetico de la cola exclusiva.');
            } else {
                $contenido = $recibido->payload();
                $nombreTrabajo = $contenido['data']['commandName'] ?? null;
                if ($nombreTrabajo !== AnalizarLoteGeminiTrabajo::class) {
                    $this->error('Redis devolvio un tipo de trabajo inesperado.');
                } else {
                    $valido = true;
                }
            }
        } catch (Throwable $error) {
            $this->error('El transporte Redis fallo. Tipo: '.class_basename($error));
        } finally {
            // Solo este trabajo de la cola aleatoria. Ante error de lectura,
            // intentar recuperarlo y retirarlo, sin inspeccionar otras colas.
            try {
                if ($recibido === null && $publicado) {
                    $recibido = $cola->pop($nombreCola);
                }
                if ($recibido !== null) {
                    $recibido->delete();
                    $retirado = true;
                }
            } catch (Throwable $error) {
                $this->warn('No se pudo confirmar retirada del ensayo: '.class_basename($error));
            }
        }

        if (! $valido || ! $retirado) {
            $this->error('Ensayo incompleto; no se debe iniciar ningun trabajador.');
            $this->line('Cola exclusiva de ensayo para diagnostico: '.$nombreCola);

            return self::FAILURE;
        }

        $this->components->info('Transporte Redis validado: escritura, lectura y retirada.');
        $this->line('Cola exclusiva utilizada: '.$nombreCola);
        $this->line('Trabajo inofensivo: AnalizarLoteGeminiTrabajo(0).');
        $this->line('No se ejecutaron trabajadores ni se llamo a Google.');

        return self::SUCCESS;
    }
}
