<?php

namespace App\Http\Controllers\IA;

use App\Http\Controllers\Controller;
use App\Models\MensajeWhatsapp;
use App\Services\IA\PoliticaEnvioGemini;
use App\Services\IA\ControlInterpretacionGemini;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

final class ControladorAnalisisIA extends Controller
{
    public function indice(PoliticaEnvioGemini $politica): View
    {
        $permitidos = DB::table('mensajero_chats_monitoreados')
            ->where('tipo_chat', 'grupo')->where('es_activo', true)
            ->where('es_monitoreado', true)->where('es_visible_tablero', true)
            ->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        $analisis = null;
        // Esta vista distingue entre resultado sin evento y ausencia de resultado.
        // La configuracion no demuestra que el trabajador siga encendido.
        $actividadIA = [
            'configurada' => $politica->configuracionCompleta(),
            'automatica' => app(ControlInterpretacionGemini::class)->estado(0)['habilitado'],
            'resultados' => 0,
            'sin_evento' => 0,
            'sin_resultado' => null,
            'ultimo_analisis' => null,
        ];

        if ($permitidos !== [] && Schema::hasTable('mensajero_analisis_operativos_ia')) {
            $base = DB::table('mensajero_analisis_operativos_ia')
                ->whereIn('chat_monitoreado_id', $permitidos);
            $actividadIA['resultados'] = (clone $base)->count();
            $actividadIA['sin_evento'] = (clone $base)->where('estado', 'sin_evento')->count();
            $actividadIA['ultimo_analisis'] = (clone $base)->max('creado_en');

            $inicio = $politica->fechaInicio();
            if ($inicio !== null) {
                $fechaUtc = $inicio->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
                $actividadIA['sin_resultado'] = DB::table('mensajero_mensajes_whatsapp as m')
                    ->whereIn('m.chat_monitoreado_id', $permitidos)
                    ->where('m.estado_captura', 'normalizado')
                    ->where('m.tipo_mensaje', 'texto')
                    ->where('m.recibido_en', '>=', $fechaUtc)
                    ->whereNotExists(function ($consulta): void {
                        $consulta->selectRaw('1')
                            ->from('mensajero_analisis_operativos_ia as a')
                            ->whereColumn('a.mensaje_whatsapp_id', 'm.id');
                    })
                    ->count();
            }

            $analisis = DB::table('mensajero_analisis_operativos_ia as a')
                ->join('mensajero_chats_monitoreados as g', 'g.id', '=', 'a.chat_monitoreado_id')
                ->whereIn('a.chat_monitoreado_id', $permitidos)
                ->where('g.tipo_chat', 'grupo')
                ->where('g.es_activo', true)
                ->where('g.es_monitoreado', true)
                ->where('g.es_visible_tablero', true)
                ->whereIn('a.estado', ['identificado_ia', 'pendiente_revision'])
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

        return view('ia.indice', compact('filas', 'analisis', 'actividadIA'));
    }
}
