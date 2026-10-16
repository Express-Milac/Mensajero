<?php

use App\Http\Controllers\Meta\ControladorWebhookWhatsapp;
use App\Http\Controllers\Pasarela\ControladorEntradaPasarela;
use Illuminate\Support\Facades\Route;

Route::get('/meta/whatsapp/webhook', [ControladorWebhookWhatsapp::class, 'verificar'])
    ->name('meta.whatsapp.webhook.verificar');

Route::post('/meta/whatsapp/webhook', [ControladorWebhookWhatsapp::class, 'recibir'])
    ->middleware('mensajero.firma_meta')
    ->name('meta.whatsapp.webhook.recibir');

Route::prefix('pasarela/v1')
    ->middleware(['mensajero.firma_pasarela', 'throttle:120,1'])
    ->group(function () {
        Route::post('/chats-descubiertos', [ControladorEntradaPasarela::class, 'sincronizarChats'])
            ->name('pasarela.v1.chats.sincronizar');
        Route::post('/eventos', [ControladorEntradaPasarela::class, 'capturarEvento'])
            ->name('pasarela.v1.eventos.capturar');
    });
