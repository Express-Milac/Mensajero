<?php

namespace App\Providers;

use App\Enums\Rol;
use App\Models\Usuario;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::define('administrar', fn (Usuario $usuario) => $usuario->rol === Rol::Administrador);
        Gate::define('revisar-eventos', fn (Usuario $usuario) => in_array($usuario->rol, [Rol::Administrador, Rol::Supervisor], true));

        RateLimiter::for('acceso', fn (Request $request) => [
            Limit::perMinute(20)->by('ip:'.$request->ip()),
            Limit::perMinute(5)->by('correo:'.hash('sha256', mb_strtolower((string) $request->input('correo')))),
        ]);
    }
}
