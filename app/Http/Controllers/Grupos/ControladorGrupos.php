<?php

namespace App\Http\Controllers\Grupos;

use App\Http\Controllers\Controller;
use App\Models\ChatMonitoreado;
use App\Services\Monitoreo\CambiarMonitoreoChat;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ControladorGrupos extends Controller
{
    public function indice(Request $request): View
    {
        $buscar = trim((string) $request->query('buscar', ''));

        $origen = $request->query('origen', 'todos');

        if (! in_array($origen, ['todos', 'recolector', 'otras'], true)) {
            $origen = 'todos';
        }


        $consulta = ChatMonitoreado::query()
            ->with('conexion')
            ->where('tipo_chat', 'grupo');

        if ($origen !== 'todos') {
            $consulta->whereHas('conexion', function ($subconsulta) use ($origen) {
                if ($origen === 'recolector') {
                    $subconsulta->where('proveedor', 'recolector_whatsapp');
                } else {
                    $subconsulta->where('proveedor', '!=', 'recolector_whatsapp');
                }
            });
        }

        if ($buscar !== '') {
            $consulta->where(function ($subconsulta) use ($buscar) {
                $subconsulta
                    ->where('nombre_grupo', 'like', '%'.$buscar.'%')
                    ->orWhere('nombre_interno', 'like', '%'.$buscar.'%')
                    ->orWhere('id_chat_proveedor', 'like', '%'.$buscar.'%');
            });
        }

        $grupos = $consulta
            ->orderByDesc('es_monitoreado')
            ->orderByDesc('visto_ultima_vez_en')
            ->orderBy('id')
            ->paginate(50)
            ->withQueryString();

        return view('grupos.indice', compact('grupos', 'buscar', 'origen'));
    }

    public function actualizarMonitoreo(
        Request $request,
        int $chat,
        CambiarMonitoreoChat $cambiarMonitoreo,
    ): RedirectResponse {
        $datos = $request->validate([
            'es_monitoreado' => ['required', 'boolean'],
            'motivo' => ['nullable', 'string', 'max:500'],
        ]);

        $actorId = auth()->id();

        try {
            $actualizado = $cambiarMonitoreo->ejecutar(
                $chat,
                $request->boolean('es_monitoreado'),
                'usuario',
                is_numeric($actorId) ? (int) $actorId : null,
                $datos['motivo'] ?? null,
            );
        } catch (DomainException $excepcion) {
            return back()->withErrors(['es_monitoreado' => $excepcion->getMessage()]);
        }

        return back()->with(
            'estado',
            $actualizado->es_monitoreado
                ? 'Grupo agregado a la lista permitida.'
                : 'Grupo retirado de la lista permitida.',
        );
    }
}
