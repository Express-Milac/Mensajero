<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class RequerirAccesoMensajero
{
    public function handle(Request $request, Closure $siguiente, string $nivel = 'usuario'): Response
    {
        $modo = (string) config('mensajero.autenticacion.modo', 'deshabilitado');

        if ($modo === 'deshabilitado') {
            if ($nivel === 'administrador') {
                abort(403);
            }

            return $siguiente($request);
        }

        if ($modo === 'corporativo') {
            abort(503, 'La autenticación corporativa de Mensajero todavía no está habilitada.');
        }

        abort_unless($modo === 'local', 500, 'Modo de autenticación de Mensajero inválido.');

        if (! Auth::check()) {
            return redirect()->guest(route('acceso'));
        }

        if ($nivel === 'administrador' && ! Gate::allows('administrar')) {
            abort(403);
        }

        return $siguiente($request);
    }
}
