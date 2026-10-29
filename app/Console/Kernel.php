<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        if (config('mensajero.normalizacion.habilitada', false)) {
            $schedule->command('mensajero:despachar-buzon --limite=100')
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
