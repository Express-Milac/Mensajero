<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/** Permiso exclusivo para activar/desactivar envíos pagados a Gemini. */
final class AutorizarGestionIA
{
    public function handle(Request $request, Closure $siguiente): Response
    {
        if (config('mensajero.autenticacion.modo') === 'local'
            && auth()->check() && Gate::allows('administrar')) {
            return $siguiente($request);
        }

        if (config('mensajero.autenticacion.modo') === 'deshabilitado'
            && app()->environment(['local', 'testing'])
            && config('mensajero.consulta_local_sin_autenticacion') === true
            && config('mensajero.gestion_local_ia_habilitada') === true
            && in_array($request->ip(), ['127.0.0.1', '::1'], true)) {
            return $siguiente($request);
        }

        abort(403, 'Cambiar la interpretación Gemini requiere permiso administrativo específico.');
    }
}
