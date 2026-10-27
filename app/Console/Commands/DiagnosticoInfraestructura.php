<?php

namespace App\Console\Commands;

use App\Services\DiagnosticoInfraestructura as ServicioDiagnostico;
use Illuminate\Console\Command;

class DiagnosticoInfraestructura extends Command
{
    protected $signature = 'mensajero:diagnostico';

    protected $description = 'Diagnóstico de infraestructura sin depender de sesiones web';

    public function handle(ServicioDiagnostico $diagnostico): int
    {
        $comprobaciones = $diagnostico->comprobar();
        $this->line(json_encode($comprobaciones, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return in_array('fallo', $comprobaciones, true) ? self::FAILURE : self::SUCCESS;
    }
}
