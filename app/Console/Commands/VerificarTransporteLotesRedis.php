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

        try {
            // ID 0 es rechazado ANTES de consultar permisos/SQL o Google en ProcesarLoteGemini.
            // El trabajo solo se transporta: no se ejecuta handle().
            $trabajo = (new AnalizarLoteGeminiTrabajo(0))->onQueue($nombreCola);
            $cola->push($trabajo, '', $nombreCola);
            $recibido = $cola->pop($nombreCola);

            if ($recibido === null) {
                $this->error('Redis no devolvio el trabajo sintetico de la cola exclusiva.');

                return self::FAILURE;
            }

            $contenido = $recibido->payload();
            $nombreTrabajo = $contenido['data']['commandName'] ?? null;
            if ($nombreTrabajo !== AnalizarLoteGeminiTrabajo::class) {
                $this->error('Redis devolvio un tipo de trabajo inesperado.');

                return self::FAILURE;
            }

            $this->components->info('Transporte Redis validado: escritura, lectura y clase del trabajo.');
            $this->line('Cola exclusiva: '.$nombreCola);
            $this->line('Identificador ficticio: 0.');
            $this->line('El trabajo NO se ejecuto y sera retirado del area reservada.');

            return self::SUCCESS;
        } catch (Throwable $error) {
            $this->error('El transporte Redis fallo. Tipo: '.class_basename($error));

            return self::FAILURE;
        } finally {
            // Solo el trabajo de la cola aleatoria creada por ESTA invocacion.
            if ($recibido !== null) {
                try {
                    $recibido->delete();
                } catch (Throwable $error) {
                    $this->warn('No se pudo confirmar retirada del ensayo Redis: '.class_basename($error));
                }
            }
        }
    }
}
