<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // El programador nunca debe vaciar un buzón histórico sin un corte
        // explícito. El comando vuelve a comprobar permisos y fechas al correr.
        if (config('mensajero.normalizacion.habilitada', false) === true
            && config('mensajero.normalizacion.despacho_automatico_protegido', false) === true) {
            $schedule->command('mensajero:despachar-buzon --automatico-protegido --limite=20')
                ->everyMinute()->withoutOverlapping();
        }

        if (config('mensajero.ia.conciliacion_automatica', false) === true) {
            $schedule->command('mensajero:conciliar-gemini --todos --limite=2 --aplicar')
                ->everyFiveMinutes()->withoutOverlapping();
        }
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
