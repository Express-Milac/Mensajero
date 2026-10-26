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

if (config('mensajero.ia.conciliacion_automatica', false) === true) {
    // Nunca programar por omisión: solo grupos aprobados, con corte nuevo,
    // máximo de dos publicaciones por grupo y cada cinco minutos.
    Schedule::command('mensajero:conciliar-gemini --todos --limite=2 --aplicar')
        ->everyFiveMinutes()
        ->withoutOverlapping();
}

if (config('mensajero.ia.conciliacion_automatica', false) === true) {
    // Recuperar proyecciones de resultados ya guardados. Sin Google ni Redis.
    // Conservar autorizaciones y no crear acontecimientos duplicados.
    Schedule::command('mensajero:proyectar-operacion --todos --limite=20 --aplicar')
        ->everyFiveMinutes()
        ->withoutOverlapping();
}
