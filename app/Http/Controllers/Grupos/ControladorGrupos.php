<?php

namespace App\Http\Controllers\Grupos;

use App\Http\Controllers\Controller;
use App\Models\ChatMonitoreado;
use App\Services\Grupos\CambiarVisibilidadTablero;
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
        $estado = $request->query('estado', 'todos');
        $visibilidad = $request->query('visibilidad', 'todos');
        if (in_array($visibilidad, ['todos', 'visibles', 'ocultos'], true) === false) {
            $visibilidad = 'todos';
        }

        if (in_array($estado, ['todos', 'monitoreados', 'no_monitoreados', 'inactivos'], true) === false) {
            $estado = 'todos';
        }

        if (in_array($origen, ['todos', 'recolector', 'otras'], true) === false) {
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

        if ($estado === 'monitoreados') {
            $consulta->where('es_monitoreado', true)->where('es_activo', true);
        } elseif ($estado === 'no_monitoreados') {
            $consulta->where('es_monitoreado', false);
        } elseif ($estado === 'inactivos') {
            $consulta->where('es_activo', false);
        }

        if ($visibilidad === 'visibles') {
            $consulta->where('es_visible_tablero', true);
        } elseif ($visibilidad === 'ocultos') {
            $consulta->where('es_visible_tablero', false);
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

        $base = ChatMonitoreado::query()->where('tipo_chat', 'grupo');
        $estadisticas = [
            'descubiertos' => (clone $base)->count(),
            'autorizados' => (clone $base)->where('es_monitoreado', true)->where('es_activo', true)->count(),
            'sin_monitorear' => (clone $base)->where('es_monitoreado', false)->count(),
            'visibles' => (clone $base)->where('es_visible_tablero', true)->where('es_monitoreado', true)->where('es_activo', true)->count(),
            'ocultos' => (clone $base)->where('es_visible_tablero', false)->count(),
        ];

        $puedeCambiarVista = (config('mensajero.autenticacion.modo') === 'local'
            && auth()->check()
            && auth()->user()->can('administrar'))
            || (config('mensajero.autenticacion.modo') === 'deshabilitado'
                && app()->environment(['local', 'testing'])
                && config('mensajero.consulta_local_sin_autenticacion', false)
                && config('mensajero.gestion_local_grupos_habilitada', false)
                && in_array($request->ip(), ['127.0.0.1', '::1'], true));

        return view('grupos.indice', compact('grupos', 'buscar', 'origen', 'estado', 'visibilidad', 'estadisticas', 'puedeCambiarVista'));
    }

    public function actualizarVisibilidad(
        Request $request,
        int $chat,
        CambiarVisibilidadTablero $cambiarVisibilidad,
    ): RedirectResponse {
        $datos = $request->validate([
            'es_visible_tablero' => ['required', 'boolean'],
        ]);

        $actorId = auth()->id();
        $actualizado = $cambiarVisibilidad->ejecutar(
            $chat,
            (bool) $datos['es_visible_tablero'],
            is_numeric($actorId) ? 'administrador' : 'gestor_local_autorizado',
            is_numeric($actorId) ? (int) $actorId : null,
        );

        return back()->with(
            'estado',
            $actualizado->es_visible_tablero
                ? 'El grupo vuelve a aparecer en el tablero. La captura no cambió.'
                : 'El grupo ya no se muestra en el tablero. La captura continúa sin cambios.',
        );
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
