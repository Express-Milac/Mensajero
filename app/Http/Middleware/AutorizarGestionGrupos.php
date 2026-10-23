<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

final class AutorizarGestionGrupos
{
    public function handle(Request $request, Closure $siguiente): Response
    {
        $modo = config('mensajero.autenticacion.modo');

        if ($modo === 'local' && auth()->check() && Gate::allows('administrar')) {
            return $siguiente($request);
        }

        // Excepción temporal explícita para controlar captura Y visibilidad.
        // Se permite solo en el propio equipo durante el POC, sin red compartida.
        if ($modo === 'deshabilitado'
            && app()->environment(['local', 'testing'])
            && config('mensajero.consulta_local_sin_autenticacion', false)
            && config('mensajero.gestion_local_grupos_habilitada', false)
            && in_array($request->ip(), ['127.0.0.1', '::1'], true)) {
            return $siguiente($request);
        }

        abort(403, 'Cambiar la captura y visibilidad requiere autorización administrativa.');
    }
}
