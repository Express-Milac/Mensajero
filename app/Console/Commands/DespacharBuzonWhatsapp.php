<?php

namespace App\Console\Commands;

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

        $resultado = $despachador->ejecutar($limite, $idEvento);

        $this->components->info(sprintf(
            'Publicados: %d. Fallidos: %d.',
            $resultado['publicados'],
            $resultado['fallidos'],
        ));

        return $resultado['fallidos'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
