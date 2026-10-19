<?php

use App\Http\Middleware\ProtegerConsultaEvidencia;
use App\Http\Middleware\RequerirAccesoMensajero;
use App\Http\Middleware\VerificarFirmaMeta;
use App\Http\Middleware\VerificarFirmaPasarela;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/disponible',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'mensajero.acceso' => RequerirAccesoMensajero::class,
            'mensajero.consulta_evidencia' => ProtegerConsultaEvidencia::class,
            'mensajero.firma_meta' => VerificarFirmaMeta::class,
            'mensajero.firma_pasarela' => VerificarFirmaPasarela::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
