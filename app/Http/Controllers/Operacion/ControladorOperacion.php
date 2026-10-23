<?php

namespace App\Http\Controllers\Operacion;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

final class ControladorOperacion extends Controller
{
    public function indice(Request $request): View
    {
        $filtros = $request->validate([
            'categoria' => ['nullable', 'in:todos,logistica,incidencia,acuerdo,solicitud,otro'],
        ]);

        $categoria = $filtros['categoria'] ?? 'todos';
        $gruposIA = (array) config('mensajero.ia.grupos_autorizados', []);
        $acontecimientos = null;
        $filas = [];
        $resumen = ['pendientes' => 0, 'adjuntos_posibles' => 0];

        if ($gruposIA !== [] && Schema::hasTable('mensajero_acontecimientos_operativos')) {
            $base = DB::table('mensajero_acontecimientos_operativos as a')
                ->join('mensajero_chats_monitoreados as g', 'g.id', '=', 'a.chat_monitoreado_id')
                ->whereIn('a.chat_monitoreado_id', $gruposIA)
                ->where('g.tipo_chat', 'grupo')
                ->where('g.es_activo', true)
                ->where('g.es_monitoreado', true)
                ->where('g.es_visible_tablero', true);

            $resumen['pendientes'] = (clone $base)->where('a.estado', 'pendiente_revision')->count();
            $resumen['adjuntos_posibles'] = DB::table('mensajero_evidencias_acontecimientos as e')
                ->join('mensajero_acontecimientos_operativos as a', 'a.id', '=', 'e.acontecimiento_id')
                ->join('mensajero_chats_monitoreados as g', 'g.id', '=', 'a.chat_monitoreado_id')
                ->whereIn('a.chat_monitoreado_id', $gruposIA)
                ->where('g.es_activo', true)->where('g.es_monitoreado', true)
                ->where('g.es_visible_tablero', true)
                ->where('e.vinculo', 'posible_adjunta')->count();

            if ($categoria !== 'todos') {
                $base->where('a.categoria', $categoria);
            }

            $acontecimientos = $base->select([
                'a.id', 'a.chat_monitoreado_id', 'a.categoria', 'a.titulo',
                'a.resumen', 'a.estado', 'a.creado_en',
                'g.nombre_interno', 'g.nombre_grupo',
            ])->orderByDesc('a.creado_en')->orderByDesc('a.id')
                ->paginate(12)->withQueryString();

            foreach ($acontecimientos as $registro) {
                $fuentes = DB::table('mensajero_evidencias_acontecimientos as e')
                    ->join('mensajero_mensajes_whatsapp as m', 'm.id', '=', 'e.mensaje_whatsapp_id')
                    ->leftJoin('mensajero_archivos_whatsapp as f', 'f.evento_crudo_id', '=', 'm.evento_crudo_id')
                    ->where('e.acontecimiento_id', $registro->id)
                    ->where('m.chat_monitoreado_id', $registro->chat_monitoreado_id)
                    ->select([
                        'e.vinculo', 'm.id as mensaje_id', 'm.evento_crudo_id',
                        'm.tipo_mensaje', 'm.pie_multimedia',
                        'f.id as archivo_id', 'f.estado as archivo_estado',
                    ])
                    ->orderBy('e.id')->limit(40)->get();

                $filas[] = ['acontecimiento' => $registro, 'fuentes' => $fuentes];
            }
        }

        return view('operacion.indice', compact('filas', 'resumen', 'acontecimientos', 'categoria'));
    }
}
