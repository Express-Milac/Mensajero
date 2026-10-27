<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    protected $middleware = [
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
        \Illuminate\Foundation\Http\Middleware\TrimStrings::class,
    ];

    protected $middlewareGroups = [
        'web' => [
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],
        'api' => [
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],
    ];

    protected $routeMiddleware = [
        'auth' => \Illuminate\Auth\Middleware\Authenticate::class,
        'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'signed' => \Illuminate\Routing\Middleware\ValidateSignature::class,
        'bindings' => \Illuminate\Routing\Middleware\SubstituteBindings::class,
        'mensajero.acceso' => \App\Http\Middleware\RequerirAccesoMensajero::class,
        'mensajero.consulta_evidencia' => \App\Http\Middleware\ProtegerConsultaEvidencia::class,
        'mensajero.gestion_grupos' => \App\Http\Middleware\AutorizarGestionGrupos::class,
        'mensajero.gestion_ia' => \App\Http\Middleware\AutorizarGestionIA::class,
        'mensajero.gestion_operacion' => \App\Http\Middleware\AutorizarGestionOperacion::class,
        'mensajero.gestion_remitentes' => \App\Http\Middleware\AutorizarGestionRemitentes::class,
        'mensajero.firma_meta' => \App\Http\Middleware\VerificarFirmaMeta::class,
        'mensajero.firma_pasarela' => \App\Http\Middleware\VerificarFirmaPasarela::class,
    ];
}
