<?php

namespace App\Http\Controllers\Autenticacion;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ControladorSesion extends Controller
{
    public function guardar(Request $request): RedirectResponse
    {
        abort_unless(config('mensajero.autenticacion.modo') === 'local', 404);

        $datos = $request->validate([
            'correo' => ['required', 'email', 'max:254'],
            'clave' => ['required', 'string', 'max:1024'],
        ]);

        $correo = mb_strtolower(trim($datos['correo']));
        if (! Auth::attempt(['correo' => $correo, 'password' => $datos['clave']])) {
            throw ValidationException::withMessages(['correo' => 'Las credenciales no son válidas.']);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('tablero'));
    }

    public function destruir(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('acceso');
    }
}
