<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

final class AutorizarGestionOperacion
{
    public function handle(Request $request, Closure $siguiente): Response
    {
        if (config('mensajero.autenticacion.modo') === 'local'
            && auth()->check()
            && Gate::allows('administrar')) {
            return $siguiente($request);
        }

        // Excepción manual solo para ensayos en la misma computadora.
        // El permiso no se hereda de la gestión de grupos.
        if (config('mensajero.autenticacion.modo') === 'deshabilitado'
            && app()->environment(['local', 'testing'])
            && config('mensajero.consulta_local_sin_autenticacion', false)
            && config('mensajero.gestion_local_operacion_habilitada', false)
            && in_array($request->ip(), ['127.0.0.1', '::1'], true)) {
            return $siguiente($request);
        }

        abort(403, 'Solo un responsable autorizado puede validar o descartar interpretaciones.');
    }
}
