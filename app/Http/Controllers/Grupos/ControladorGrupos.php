<?php

namespace App\Http\Controllers\Grupos;

use App\Http\Controllers\Controller;
use App\Models\ChatMonitoreado;
use App\Services\Monitoreo\CambiarMonitoreoChat;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ControladorGrupos extends Controller
{
    public function indice(Request $request): View
    {
        $buscar = trim((string) $request->query('buscar', ''));

        $origen = $request->query('origen', 'todos');
        $estado = $request->query('estado', 'todos');
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
            'no_habilitados' => (clone $base)->where(function ($consulta): void {
                $consulta->where('es_monitoreado', false)->orWhere('es_activo', false);
            })->count(),
        ];

        $puedeCambiarCaptura = (config('mensajero.autenticacion.modo') === 'local'
            && auth()->check()
            && auth()->user()->can('administrar'))
            || (config('mensajero.autenticacion.modo') === 'deshabilitado'
                && app()->environment(['local', 'testing'])
                && config('mensajero.consulta_local_sin_autenticacion', false)
                && config('mensajero.gestion_local_grupos_habilitada', false)
                && in_array($request->ip(), ['127.0.0.1', '::1'], true));

        return view('grupos.indice', compact('grupos', 'buscar', 'origen', 'estado', 'visibilidad', 'estadisticas', 'puedeCambiarVista'));
    }

    public function actualizarMonitoreo(
        Request $request,
        int $chat,
        CambiarMonitoreoChat $cambiarMonitoreo,
    ): RedirectResponse|JsonResponse {
        $datos = $request->validate([
            'es_monitoreado' => ['required', 'boolean'],
            'motivo' => ['nullable', 'string', 'max:500'],
        ]);

        $actorId = auth()->id();
        $nuevoEstado = $request->boolean('es_monitoreado');

        try {
            $actualizado = $cambiarMonitoreo->ejecutar(
                $chat,
                $nuevoEstado,
                is_numeric($actorId) ? 'usuario' : 'gestor_local_autorizado',
                is_numeric($actorId) ? (int) $actorId : null,
                $datos['motivo'] ?? null,
            );
        } catch (DomainException $excepcion) {
            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'mensaje' => $excepcion->getMessage(),
                ], 422)->header('Cache-Control', 'private, no-store, max-age=0');
            }

            return back()->withErrors(['es_monitoreado' => $excepcion->getMessage()]);
        }

        $mensaje = $actualizado->es_monitoreado
            ? 'Grupo habilitado para captura y visible en el tablero.'
            : 'Captura desactivada y grupo oculto del tablero. Se conservan los mensajes anteriores.';

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'grupo_id' => (int) $actualizado->id,
                'es_monitoreado' => (bool) $actualizado->es_monitoreado,
                'es_visible_tablero' => (bool) $actualizado->es_visible_tablero,
                'mensaje' => $mensaje,
            ])->header('Cache-Control', 'private, no-store, max-age=0');
        }

        return back()->with('estado', $mensaje);
    }
}
