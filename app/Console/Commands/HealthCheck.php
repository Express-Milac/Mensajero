<?php

namespace App\Console\Commands;

use App\Services\InfrastructureHealth;
use Illuminate\Console\Command;

class HealthCheck extends Command
{
    protected $signature = 'mensajero:health';

    protected $description = 'Diagnóstico de infraestructura sin depender de sesiones web';

    public function handle(InfrastructureHealth $health): int
    {
        $checks = $health->check();
        $this->line(json_encode($checks, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return in_array('failed', $checks, true) ? self::FAILURE : self::SUCCESS;
    }
}
