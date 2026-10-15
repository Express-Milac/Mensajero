<?php

use App\Http\Controllers\Pasarela\ControladorEntradaPasarela;
use Illuminate\Support\Facades\Route;

Route::prefix('pasarela/v1')->group(function () {
    Route::post('/chats-autorizados', [ControladorEntradaPasarela::class, 'chatsAutorizados'])
        ->middleware(['mensajero.firma_pasarela:no', 'throttle:60,1'])
        ->name('pasarela.v1.chats.autorizados');

    Route::middleware(['mensajero.firma_pasarela', 'throttle:120,1'])->group(function () {
        Route::post('/estado-conexion', [ControladorEntradaPasarela::class, 'actualizarEstado'])
            ->name('pasarela.v1.estado.actualizar');
        Route::post('/chats-descubiertos', [ControladorEntradaPasarela::class, 'sincronizarChats'])
            ->name('pasarela.v1.chats.sincronizar');
        Route::post('/eventos', [ControladorEntradaPasarela::class, 'capturarEvento'])
            ->name('pasarela.v1.eventos.capturar');
    });
});
