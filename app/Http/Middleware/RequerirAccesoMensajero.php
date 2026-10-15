<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class RequerirAccesoMensajero
{
    public function handle(Request $request, Closure $next, string $nivel = 'usuario'): Response
    {
        $modo = (string) config('mensajero.autenticacion.modo', 'deshabilitado');

        if ($modo === 'deshabilitado') {
            if ($nivel === 'administracion' && ! $this->ipAdministrativaPermitida($request->ip())) {
                abort(403, 'Acceso administrativo restringido mientras no exista autenticación corporativa.');
            }

            return $next($request);
        }

        if ($modo === 'corporativo') {
            abort(503, 'La autenticación corporativa de Mensajero todavía no está habilitada.');
        }

        abort_unless($modo === 'local', 500, 'Modo de autenticación de Mensajero inválido.');

        if (! Auth::check()) {
            return redirect()->guest(route('acceso'));
        }

        if ($nivel === 'administracion' && ! Gate::allows('administrar')) {
            abort(403);
        }

        return $next($request);
    }

    private function ipAdministrativaPermitida(?string $ip): bool
    {
        if ($ip === null) {
            return false;
        }

        $permitidas = config('mensajero.autenticacion.ips_administracion', ['127.0.0.1', '::1']);

        return in_array($ip, $permitidas, true);
    }
}
