<?php

use App\Http\Controllers\Administracion\ControladorEdicionConexionesWhatsapp;
use Illuminate\Support\Facades\Route;

// Este archivo es incluido por la gestión existente de WhatsApp, que a su vez
// usa el middleware web (CSRF). No altera ninguna ruta de SSO corporativo.
Route::prefix('administracion/whatsapp')
    ->middleware('mensajero.acceso:administrador')
    ->name('whatsapp.edicion.')
    ->group(function (): void {
        Route::patch('/conexiones/{conexion}', [ControladorEdicionConexionesWhatsapp::class, 'actualizar'])
            ->whereNumber('conexion')->middleware('throttle:15,1')->name('actualizar');
        Route::delete('/conexiones/{conexion}', [ControladorEdicionConexionesWhatsapp::class, 'eliminar'])
            ->whereNumber('conexion')->middleware('throttle:5,1')->name('eliminar');
    });