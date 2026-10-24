<?php

namespace App\Http\Controllers\IA;

use App\Http\Controllers\Controller;
use App\Models\ChatMonitoreado;
use App\Services\IA\ControlInterpretacionGemini;
use App\Services\IA\PoliticaEnvioGemini;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class ControladorConfiguracionGemini extends Controller
{
    public function indice(ControlInterpretacionGemini $control, PoliticaEnvioGemini $politica): View
    {
        $autorizados = array_map('intval', (array) config('mensajero.ia.grupos_autorizados', []));

        $grupos = ChatMonitoreado::query()
            ->where('tipo_chat', 'grupo')
            ->where('es_activo', true)
            ->where('es_monitoreado', true)
            ->where('es_visible_tablero', true)
            ->orderBy('nombre_interno')
            ->orderBy('nombre_grupo')
            ->get(['id', 'nombre_grupo', 'nombre_interno', 'es_activo', 'es_monitoreado', 'es_visible_tablero']);

        $filas = $grupos->map(static function (ChatMonitoreado $grupo) use ($autorizados, $control): array {
            return [
                'grupo' => $grupo,
                'preautorizado' => in_array((int) $grupo->id, $autorizados, true),
                'estado' => $control->estado((int) $grupo->id),
            ];
        });

        $general = $control->estado(0);
        $disponible = $control->disponible();
        $listo = $politica->configuracionCompleta();
        $maximo = max(0, min(30, (int) config('mensajero.ia.conciliacion_maximo_dia_grupo', 10)));

        return view('ia.configuracion', compact('filas', 'general', 'disponible', 'listo', 'maximo'));
    }

    public function cambiar(
        Request $request,
        int $grupo,
        ControlInterpretacionGemini $control,
    ): RedirectResponse {
        $datos = $request->validate([
            'habilitado' => ['required', 'boolean'],
        ]);
        $habilitar = $request->boolean('habilitado');

        if ($habilitar && ! $request->boolean('confirmo_envio')) {
            return back()->withErrors(['habilitado' => 'Confirma expresamente el envío de nuevos mensajes de este ámbito a Gemini.']);
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
