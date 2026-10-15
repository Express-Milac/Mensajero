<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class RequireMensajeroAccess
{
    public function handle(Request $request, Closure $next, string $level = 'user'): Response
    {
        $mode = (string) config('mensajero.auth.mode', 'disabled');

        if ($mode === 'disabled') {
            return $next($request);
        }

        if ($mode === 'corporate') {
            abort(503, 'La autenticación corporativa de Mensajero todavía no está habilitada.');
        }

        abort_unless($mode === 'local', 500, 'Modo de autenticación de Mensajero inválido.');

        if (! Auth::check()) {
            return redirect()->guest(route('login'));
        }

        if ($level === 'admin' && ! Gate::allows('administer')) {
            abort(403);
        }

        return $next($request);
    }
}
