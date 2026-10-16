<?php

use App\Http\Controllers\Meta\ControladorWebhookWhatsapp;
use App\Http\Controllers\Pasarela\ControladorEntradaPasarela;
use Illuminate\Support\Facades\Route;

Route::get('/meta/whatsapp/webhook', [ControladorWebhookWhatsapp::class, 'verificar'])
    ->name('meta.whatsapp.webhook.verificar');

Route::post('/meta/whatsapp/webhook', [ControladorWebhookWhatsapp::class, 'recibir'])
    ->middleware('mensajero.firma_meta')
    ->name('meta.whatsapp.webhook.recibir');

Route::prefix('pasarela/v1')->group(function () {
    Route::post('/chats-descubiertos', [ControladorEntradaPasarela::class, 'sincronizarChats'])
        ->middleware(['mensajero.firma_pasarela', 'throttle:120,1'])
        ->name('pasarela.v1.chats.sincronizar');
    Route::post('/eventos', [ControladorEntradaPasarela::class, 'capturarEvento'])
        ->middleware(['mensajero.firma_pasarela', 'throttle:1200,1'])
        ->name('pasarela.v1.eventos.capturar');
    Route::post('/integridad', [ControladorEntradaPasarela::class, 'registrarIntegridad'])
        ->middleware(['mensajero.firma_pasarela', 'throttle:120,1'])
        ->name('pasarela.v1.integridad.registrar');
    Route::post('/chats-autorizados', [ControladorEntradaPasarela::class, 'chatsAutorizados'])
        ->middleware(['mensajero.firma_pasarela:no', 'throttle:120,1'])
        ->name('pasarela.v1.chats.autorizados');
    Route::post('/estado-conexion', [ControladorEntradaPasarela::class, 'actualizarEstado'])
        ->middleware(['mensajero.firma_pasarela:no', 'throttle:240,1'])
        ->name('pasarela.v1.estado.actualizar');
});
