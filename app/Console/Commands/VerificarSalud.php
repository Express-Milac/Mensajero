<?php

namespace App\Console\Commands;

use App\Services\SaludInfraestructura;
use Illuminate\Console\Command;

class VerificarSalud extends Command
{
    protected $signature = 'mensajero:verificar-salud';

    protected $description = 'Diagnóstico de infraestructura sin depender de sesiones web';

    public function handle(SaludInfraestructura $salud): int
    {
        $comprobaciones = $salud->verificar();
        $this->line(json_encode($comprobaciones, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return in_array('fallo', $comprobaciones, true) ? self::FAILURE : self::SUCCESS;
    }
}
