<?php

namespace App\Http\Controllers\Autenticacion;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ControladorSesion extends Controller
{
    public function iniciar(Request $solicitud): RedirectResponse
    {
        abort_unless(config('mensajero.autenticacion.modo') === 'local', 404);

        $credenciales = $solicitud->validate([
            'email' => ['required', 'email', 'max:254'],
            'password' => ['required', 'string', 'max:1024'],
        ]);
        $credenciales['email'] = mb_strtolower(trim($credenciales['email']));

        if (! Auth::attempt($credenciales)) {
            throw ValidationException::withMessages(['email' => 'Las credenciales no son válidas.']);
        }

        $solicitud->session()->regenerate();

        return redirect()->intended(route('tablero'));
    }

    public function salir(Request $solicitud): RedirectResponse
    {
        if (config('mensajero.autenticacion.modo') !== 'local') {
            return redirect()->route('tablero');
        }

        Auth::logout();
        $solicitud->session()->invalidate();
        $solicitud->session()->regenerateToken();

        return redirect()->route('acceso');
    }
}
