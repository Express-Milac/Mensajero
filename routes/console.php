<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

if (config('mensajero.normalizacion.habilitada', false)) {
    Schedule::command('mensajero:despachar-buzon --limite=100')
        ->everyMinute()
        ->withoutOverlapping();
}
