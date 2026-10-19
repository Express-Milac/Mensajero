<?php

namespace App\Console\Commands;

use App\Models\EventoCrudoWhatsapp;
use App\Services\Normalizacion\DespacharBuzonWhatsapp as ServicioDespacharBuzon;
use Illuminate\Console\Command;

final class DespacharBuzonWhatsapp extends Command
{
    protected $signature = 'mensajero:despachar-buzon
        {--limite=100 : Máximo de elementos por ejecución (1 a 500)}
        {--evento-id= : Procesar únicamente un evento crudo, útil para diagnóstico}';

    protected $description = 'Publica eventos crudos pendientes de SQL Server a la cola Redis de normalización';

    public function handle(ServicioDespacharBuzon $despachador): int
    {
        $limite = filter_var($this->option('limite'), FILTER_VALIDATE_INT);
        $evento = $this->option('evento-id');
        $idEvento = $evento === null ? null : filter_var($evento, FILTER_VALIDATE_INT);

        if ($limite === false || $limite < 1 || $limite > 500
            || ($evento !== null && ($idEvento === false || $idEvento < 1))) {
            $this->error('Los parámetros deben ser enteros positivos; límite máximo: 500.');

            return self::FAILURE;
        }

        // Sin activación automática, impedir publicar el histórico completo
        // por accidente; permitir solo ensayos con un evento específico.
        if (config('mensajero.normalizacion.habilitada', false) !== true) {
            if (! app()->environment(['local', 'testing'])
                || config('database.default') !== 'sqlsrv'
                || ! str_ends_with((string) config('database.connections.sqlsrv.database'), '_test')
                || $idEvento === null
                || $limite !== 1) {
                $this->error('Publicación bloqueada: con normalización automática inactiva solo se admite un evento explícito, --limite=1 y SQL Server _test en entorno local/de pruebas.');

                return self::FAILURE;
            }

            $autorizado = EventoCrudoWhatsapp::query()
                ->whereKey($idEvento)
                ->where('estado_procesamiento', 'capturado')
                ->whereHas('chat', function ($consulta) {
                    $consulta->where('tipo_chat', 'grupo')
                        ->where('es_monitoreado', true)
                        ->where('es_activo', true);
                })
                ->exists();

            if (! $autorizado) {
                $this->error('El evento de prueba no está capturado o su grupo no está activo y autorizado.');

                return self::FAILURE;
            }
        }

        $resultado = $despachador->ejecutar($limite, $idEvento);

        $this->components->info(sprintf(
            'Publicados: %d. Fallidos: %d.',
            $resultado['publicados'],
            $resultado['fallidos'],
        ));

        return $resultado['fallidos'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
