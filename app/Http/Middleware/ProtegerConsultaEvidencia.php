<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * En modo temporal sin login, nunca expone mensajes a una red por omisión.
 * El acceso local debe activarse explícitamente y solo desde bucle local.
 */
final class ProtegerConsultaEvidencia
{
    public function handle(Request $request, Closure $siguiente): Response
    {
        if (config('mensajero.autenticacion.modo') === 'deshabilitado') {
            abort_unless(
                app()->environment(['local', 'testing'])
                    && config('mensajero.consulta_local_sin_autenticacion', false)
                    && in_array($request->ip(), ['127.0.0.1', '::1'], true),
                403,
                'La consulta de mensajes requiere autenticación o una autorización local explícita.',
            );
        }

        return $siguiente($request);
    }
}
