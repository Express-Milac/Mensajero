<?php

use App\Http\Controllers\Autenticacion\ControladorSesion;
use App\Servicios\SaludInfraestructura;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/tablero');

Route::get('/acceso', function () {
    if (config('mensajero.autenticacion.modo') !== 'local') {
        return redirect()->route('tablero');
    }

    return view('autenticacion.acceso');
})->name('acceso');

Route::post('/acceso', [ControladorSesion::class, 'guardar'])
    ->middleware('throttle:acceso')
    ->name('acceso.guardar');

Route::middleware('mensajero.acceso')->group(function () {
    Route::post('/salir', [ControladorSesion::class, 'destruir'])->name('salir');
    Route::view('/tablero', 'tablero')->name('tablero');

    Route::get('/salud', function (SaludInfraestructura $salud) {
        $comprobaciones = $salud->verificar();

        return response()->json(
            ['comprobaciones' => $comprobaciones],
            in_array('fallo', $comprobaciones, true) ? 503 : 200,
        );
    })->middleware(['mensajero.acceso:administracion', 'throttle:10,1'])->name('salud');
});
