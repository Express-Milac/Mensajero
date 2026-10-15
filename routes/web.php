<?php

use App\Http\Controllers\Autenticacion\ControladorSesion;
use App\Services\DiagnosticoInfraestructura;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/tablero');

Route::get('/acceso', function () {
    if (config('mensajero.autenticacion.modo') !== 'local') {
        return redirect()->route('tablero');
    }

    return view('autenticacion.acceso');
})->name('acceso');

Route::post('/acceso', [ControladorSesion::class, 'iniciar'])
    ->middleware('throttle:acceso')
    ->name('acceso.iniciar');

Route::middleware('mensajero.acceso')->group(function () {
    Route::post('/salir', [ControladorSesion::class, 'salir'])->name('salir');
    Route::view('/tablero', 'tablero')->name('tablero');

    Route::get('/estado', function (DiagnosticoInfraestructura $diagnostico) {
        $comprobaciones = $diagnostico->comprobar();

        return response()->json(
            ['comprobaciones' => $comprobaciones],
            in_array('fallo', $comprobaciones, true) ? 503 : 200,
        );
    })->middleware(['mensajero.acceso:administrador', 'throttle:10,1'])->name('estado');
});
