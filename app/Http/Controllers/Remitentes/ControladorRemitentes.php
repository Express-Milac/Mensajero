<?php

namespace App\Http\Controllers\Remitentes;

use App\Http\Controllers\Controller;
use App\Models\ChatMonitoreado;
use App\Models\EventoCrudoWhatsapp;
use App\Models\IdentidadRemitente;
use App\Services\Mensajes\IdentificarRemitente;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class ControladorRemitentes extends Controller
{
    public function indice(Request $request, IdentificarRemitente $identificador): View
    {
        $datos = $request->validate(['grupo' => ['nullable', 'integer', 'min:1']]);
        $grupoId = isset($datos['grupo']) ? (int) $datos['grupo'] : null;

        $grupos = ChatMonitoreado::query()
            ->where('tipo_chat', 'grupo')
            ->where('es_monitoreado', true)
            ->where('es_activo', true)
            ->orderBy('nombre_grupo')
            ->get(['id', 'nombre_grupo', 'nombre_interno']);

        $ids = $grupos->pluck('id')->all();
        $consulta = EventoCrudoWhatsapp::query()
            ->whereIn('chat_monitoreado_id', $ids)
            ->where('tipo_evento', 'mensaje')
            ->whereNotNull('id_remitente_proveedor')
            ->where('id_remitente_proveedor', '!=', '');

        if ($grupoId !== null) {
            abort_unless(in_array($grupoId, $ids, true), 404);
            $consulta->where('chat_monitoreado_id', $grupoId);
        }

        $remitentes = $consulta
            ->select(['conexion_id', 'id_remitente_proveedor'])
            ->selectRaw('MAX(id) AS ultimo_evento_id')
            ->selectRaw('COUNT_BIG(*) AS mensajes')
            ->groupBy('conexion_id', 'id_remitente_proveedor')
            ->orderByDesc('ultimo_evento_id')
            ->paginate(30)
            ->withQueryString();

        $eventos = EventoCrudoWhatsapp::query()
            ->with(['chat', 'mensaje.remitente'])
            ->whereIn('id', collect($remitentes->items())->pluck('ultimo_evento_id')->all())
            ->get()
            ->keyBy('id');

        $identificador->precargar($eventos);

        $filas = [];
        foreach ($remitentes as $fila) {
            $evento = $eventos->get((int) $fila->ultimo_evento_id);
            if ($evento === null) {
                continue;
            }

            $filas[] = [
                'evento' => $evento,
                'nombre' => $identificador->nombre($evento),
                'nombre_asignado' => $identificador->nombreAsignado($evento),
                'nombre_whatsapp' => $identificador->nombreWhatsapp($evento),
                'mensajes' => (int) $fila->mensajes,
            ];
        }

        return view('remitentes.indice', compact('grupos', 'grupoId', 'remitentes', 'filas'));
    }

    public function editar(int $evento, IdentificarRemitente $identificador): View
    {
        $registro = $this->eventoAutorizado($evento);
        abort_unless(is_string($registro->id_remitente_proveedor) && $registro->id_remitente_proveedor !== '', 404);

        $identificador->precargar(collect([$registro]));

        return view('remitentes.editar', [
            'registro' => $registro,
            'nombre' => $identificador->nombre($registro),
            'nombreWhatsapp' => $identificador->nombreWhatsapp($registro),
            'nombreAsignado' => $identificador->nombreAsignado($registro),
        ]);
    }

    public function actualizar(Request $request, int $evento): RedirectResponse
    {
        $registro = $this->eventoAutorizado($evento);
        $id = $registro->id_remitente_proveedor;
        abort_unless(is_string($id) && $id !== '', 404);

        $datos = $request->validate([
            'nombre_interno' => ['required', 'string', 'max:120'],
        ]);

        $nombre = trim($datos['nombre_interno']);
        if ($nombre === '' || preg_match('/[\\x00-\\x1F\\x7F]/u', $nombre) === 1) {
            return back()->withErrors(['nombre_interno' => 'Escribe un nombre legible, sin caracteres de control.']);
        }

        $actorId = auth()->id();
        DB::transaction(function () use ($registro, $id, $nombre, $actorId): void {
            IdentidadRemitente::query()->updateOrCreate(
                ['conexion_id' => $registro->conexion_id, 'id_remitente_proveedor' => $id],
                [
                    'nombre_interno' => $nombre,
                    'actor_tipo' => is_numeric($actorId) ? 'administrador' : 'gestor_local_autorizado',
                    'actor_id' => is_numeric($actorId) ? (int) $actorId : null,
                ],
            );
        });

        return redirect()->route('remitentes.editar', $registro->id)
            ->with('estado', 'Nombre guardado. Se mostrará en los mensajes anteriores y futuros de esta misma identidad.');
    }

    private function eventoAutorizado(int $evento): EventoCrudoWhatsapp
    {
        return EventoCrudoWhatsapp::query()
            ->with(['chat', 'mensaje.remitente'])
            ->where('tipo_evento', 'mensaje')
            ->whereHas('chat', fn ($consulta) => $consulta
                ->where('tipo_chat', 'grupo')
                ->where('es_monitoreado', true)
                ->where('es_activo', true))
            ->findOrFail($evento);
    }
}
