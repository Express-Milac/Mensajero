<?php

namespace App\Http\Controllers\IA;

use App\Http\Controllers\Controller;
use App\Models\MensajeWhatsapp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

final class ControladorAnalisisIA extends Controller
{
    public function indice(): View
    {
        $permitidos = (array) config('mensajero.ia.grupos_autorizados', []);
        $analisis = null;

        if ($permitidos !== [] && Schema::hasTable('mensajero_analisis_operativos_ia')) {
            $analisis = DB::table('mensajero_analisis_operativos_ia as a')
                ->join('mensajero_chats_monitoreados as g', 'g.id', '=', 'a.chat_monitoreado_id')
                ->whereIn('a.chat_monitoreado_id', $permitidos)
                ->where('g.tipo_chat', 'grupo')
                ->where('g.es_activo', true)
                ->where('g.es_monitoreado', true)
                ->where('a.estado', 'pendiente_revision')
                ->select([
                    'a.id', 'a.chat_monitoreado_id', 'a.categoria', 'a.titulo',
                    'a.resumen', 'a.evidencias_json', 'a.creado_en',
                    'g.nombre_interno', 'g.nombre_grupo',
                ])
                ->orderByDesc('a.id')
                ->paginate(20);
        }

        $filas = [];
        foreach ($analisis ?? [] as $registro) {
            $ids = json_decode((string) $registro->evidencias_json, true);
            $ids = is_array($ids) ? array_filter($ids, 'is_int') : [];

            $fuentes = MensajeWhatsapp::query()
                ->where('chat_monitoreado_id', $registro->chat_monitoreado_id)
                ->whereIn('id', $ids)
                ->get(['id', 'evento_crudo_id'])
                ->sortBy(fn ($mensaje): int => (int) array_search((int) $mensaje->id, $ids, true));

            $filas[] = ['registro' => $registro, 'fuentes' => $fuentes];
        }

        return view('ia.indice', compact('filas', 'analisis'));
    }
}
