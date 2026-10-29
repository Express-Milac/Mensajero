<?php

namespace App\Providers;

use App\Enums\Rol;
use App\Models\Usuario;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Sin servicios adicionales por registrar en esta etapa.
    }

    public function boot(): void
    {
        // Apache publica Mensajero debajo de /Mensajero. La ruta en APP_URL
        // es la raiz publica de la aplicacion, no una ruta Laravel.
        // No aplicar prefijos a Route::get(): Laravel y el servidor mantienen
        // las rutas internas sin /Mensajero (tambien en Windows localhost).
        $urlPublica = rtrim((string) config('app.url', ''), '/');
        $partes = parse_url($urlPublica);
        if (is_array($partes)
            && in_array($partes['scheme'] ?? '', ['https', 'http'], true)
            && ! empty($partes['host'])
            && ! empty($partes['path'])
            && trim((string) $partes['path'], '/') !== '') {
            URL::forceRootUrl($urlPublica);
            URL::forceScheme($partes['scheme']);
        }

        Gate::define('administrar', fn (Usuario $usuario) => $usuario->rol === Rol::Administrador);
        Gate::define('revisar-eventos', fn (Usuario $usuario) => in_array($usuario->rol, [Rol::Administrador, Rol::Supervisor], true));

        RateLimiter::for('acceso', fn (Request $request) => [
            Limit::perMinute(20)->by('ip:'.$request->ip()),
            Limit::perMinute(5)->by('correo:'.hash('sha256', mb_strtolower((string) $request->input('email')))),
        ]);
    }
}
