<?php

use App\Http\Controllers\Autenticacion\ControladorSesion;
use App\Http\Controllers\Grupos\ControladorGrupos;
use App\Http\Controllers\Mensajes\ControladorArchivosWhatsapp;
use App\Http\Controllers\Mensajes\ControladorMensajes;
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
    Route::get('/tablero', [ControladorMensajes::class, 'indice'])
        ->middleware('mensajero.consulta_evidencia')
        ->name('tablero');
    Route::get('/grupos', [ControladorGrupos::class, 'indice'])->name('grupos.indice');

    Route::middleware('mensajero.consulta_evidencia')->group(function () {
        Route::get('/mensajes', [ControladorMensajes::class, 'indice'])
            ->name('mensajes.indice');
        Route::get('/mensajes/actualizacion', [ControladorMensajes::class, 'actualizacion'])
            ->middleware('throttle:15,1')
            ->name('mensajes.actualizacion');
        Route::get('/mensajes/tecnico', [ControladorMensajes::class, 'tecnico'])
            ->middleware('mensajero.acceso:administrador')
            ->name('mensajes.tecnico');
        Route::get('/mensajes/grupos/{chat}', [ControladorMensajes::class, 'grupo'])
            ->whereNumber('chat')
            ->name('mensajes.grupo');
        Route::get('/mensajes/eventos/{evento}', [ControladorMensajes::class, 'evento'])
            ->whereNumber('evento')
            ->name('mensajes.evento');
        Route::get('/mensajes/archivos/{archivo}', [ControladorArchivosWhatsapp::class, 'ver'])
            ->whereNumber('archivo')
            ->middleware('throttle:60,1')
            ->name('mensajes.archivo.ver');
        Route::get('/mensajes/archivos/{archivo}/descargar', [ControladorArchivosWhatsapp::class, 'descargar'])
            ->whereNumber('archivo')
            ->middleware('throttle:30,1')
            ->name('mensajes.archivo.descargar');
    });

    Route::patch('/grupos/{chat}/monitoreo', [ControladorGrupos::class, 'actualizarMonitoreo'])
        ->whereNumber('chat')
        ->middleware('mensajero.acceso:administrador')
        ->name('grupos.monitoreo.actualizar');

    Route::get('/estado', function (DiagnosticoInfraestructura $diagnostico) {
        $comprobaciones = $diagnostico->comprobar();

        return response()->json(
            ['comprobaciones' => $comprobaciones],
            in_array('fallo', $comprobaciones, true) ? 503 : 200,
        );
    })->middleware(['mensajero.acceso:administrador', 'throttle:10,1'])->name('estado');
});
