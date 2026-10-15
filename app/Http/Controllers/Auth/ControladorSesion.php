<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ControladorSesion extends Controller
{
    public function iniciar(Request $request): RedirectResponse
    {
        abort_unless(config('mensajero.autenticacion.modo') === 'local', 404);

        $credenciales = $request->validate([
            'email' => ['required', 'email', 'max:254'],
            'password' => ['required', 'string', 'max:1024'],
        ]);
        $credenciales['email'] = mb_strtolower(trim($credenciales['email']));

        if (! Auth::attempt($credenciales)) {
            throw ValidationException::withMessages(['email' => 'Las credenciales no son válidas.']);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('tablero'));
    }

    public function salir(Request $request): RedirectResponse
    {
        if (config('mensajero.autenticacion.modo') !== 'local') {
            return redirect()->route('tablero');
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('acceso');
    }
}
