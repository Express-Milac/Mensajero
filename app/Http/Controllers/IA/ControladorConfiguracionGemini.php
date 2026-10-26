<?php

namespace App\Http\Controllers\IA;

use App\Http\Controllers\Controller;
use App\Models\ChatMonitoreado;
use App\Services\IA\ControlInterpretacionGemini;
use App\Services\IA\PoliticaEnvioGemini;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class ControladorConfiguracionGemini extends Controller
{
    public function indice(ControlInterpretacionGemini $control, PoliticaEnvioGemini $politica): View
    {
        $grupos = ChatMonitoreado::query()
            ->where('tipo_chat', 'grupo')
            ->where('es_activo', true)
            ->where('es_monitoreado', true)
            ->where('es_visible_tablero', true)
            ->orderBy('nombre_interno')
            ->orderBy('nombre_grupo')
            ->get(['id', 'nombre_grupo', 'nombre_interno', 'es_activo', 'es_monitoreado', 'es_visible_tablero']);

        $filas = $grupos->map(static function (ChatMonitoreado $grupo) use ($control): array {
            return [
                'grupo' => $grupo,
                'estado' => $control->estado((int) $grupo->id),
            ];
        });

        $general = $control->estado(0);
        $disponible = $control->disponible();
        $listo = $politica->configuracionCompleta();
        $limite = (int) config('mensajero.ia.conciliacion_maximo_dia_grupo', 10);
        $maximo = $limite >= 1 && $limite <= 30 ? $limite : 0;

        return view('ia.configuracion', compact('filas', 'general', 'disponible', 'listo', 'maximo'));
    }

    public function cambiar(
        Request $request,
        int $grupo,
        ControlInterpretacionGemini $control,
    ): JsonResponse|RedirectResponse {
        $datos = $request->validate([
            'habilitado' => ['required', 'boolean'],
        ]);
        $habilitar = $request->boolean('habilitado');

        if ($habilitar && ! $request->boolean('confirmo_envio')) {
            $mensaje = 'Confirma expresamente el envío de nuevos mensajes de este ámbito a Gemini.';
            return $request->expectsJson()
                ? response()->json(['mensaje' => $mensaje], 422)
                : back()->withErrors(['habilitado' => $mensaje]);
        }

        $actorId = auth()->id();
        try {
            $control->cambiar(
                $grupo,
                $habilitar,
                is_numeric($actorId) ? 'administrador' : 'gestor_local_autorizado',
                is_numeric($actorId) ? (int) $actorId : null,
            );
        } catch (DomainException $error) {
            return back()->withErrors(['habilitado' => $error->getMessage()]);
        }

        $ambito = $grupo === 0 ? 'Interpretación general' : 'Interpretación del grupo #'.$grupo;
        return back()->with('estado', $ambito.($habilitar
            ? ' activada. Solo mensajes recibidos desde la activación; no se recupera historial.'
            : ' desactivada. No se iniciarán nuevos análisis de este ámbito.'));
    }
}
