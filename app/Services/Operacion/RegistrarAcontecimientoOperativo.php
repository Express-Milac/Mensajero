<?php

namespace App\Services\Operacion;

use App\Models\MensajeWhatsapp;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Proyección independiente de un resultado IA aceptado.
 * Ningún texto bruto se sobrescribe, ni se llama a Google.
 */
final class RegistrarAcontecimientoOperativo
{
    public function __construct(private readonly VincularEvidenciasOperativas $vinculador) {}

    public function desdeAnalisis(int $analisisId): ?int
    {
        if (! Schema::hasTable('mensajero_acontecimientos_operativos')) {
            return null;
        }

        try {
            $id = DB::transaction(function () use ($analisisId): ?int {
                $analisis = DB::table('mensajero_analisis_operativos_ia')->where('id', $analisisId)
                    ->lockForUpdate()->first();

                if ($analisis === null || ! in_array($analisis->estado, ['identificado_ia', 'pendiente_revision'], true)) {
                    return null;
                }

                $grupo = DB::table('mensajero_chats_monitoreados')
                    ->where('id', $analisis->chat_monitoreado_id)
                    ->where('es_activo', true)->where('es_monitoreado', true)
                    ->where('es_visible_tablero', true)->first(['id']);
                if ($grupo === null || ! in_array((int) $grupo->id, (array) config('mensajero.ia.grupos_autorizados', []), true)) {
                    return null;
                }

                $anterior = DB::table('mensajero_acontecimientos_operativos')
                    ->where('analisis_ia_id', $analisisId)->value('id');
                if ($anterior !== null) {
                    return (int) $anterior;
                }

                $objetivo = MensajeWhatsapp::query()
                    ->whereKey($analisis->mensaje_whatsapp_id)
                    ->where('chat_monitoreado_id', $analisis->chat_monitoreado_id)
                    ->where('estado_captura', 'normalizado')->first(['id', 'chat_monitoreado_id']);
                if ($objetivo === null) {
                    return null;
                }

                $fuentes = json_decode((string) $analisis->evidencias_json, true);
                if (! is_array($fuentes) || ! array_is_list($fuentes) || ! in_array((int) $objetivo->id, $fuentes, true)) {
                    return null;
                }

                $ids = array_values(array_unique(array_filter($fuentes, 'is_int')));
                if (count($ids) < 1 || count($ids) > 6) {
                    return null;
                }

                $cuenta = MensajeWhatsapp::query()->where('chat_monitoreado_id', $objetivo->chat_monitoreado_id)
                    ->whereIn('id', $ids)->where('estado_captura', 'normalizado')->count();
                if ($cuenta !== count($ids)) {
                    return null;
                }

                $ahora = now('UTC')->format('Y-m-d H:i:s.u');
                $nuevo = DB::table('mensajero_acontecimientos_operativos')->insertGetId([
                    'analisis_ia_id' => $analisisId,
                    'chat_monitoreado_id' => (int) $grupo->id,
                    'mensaje_objetivo_id' => (int) $objetivo->id,
                    'categoria' => (string) $analisis->categoria,
                    'titulo' => (string) $analisis->titulo,
                    'resumen' => (string) $analisis->resumen,
                    'estado' => 'generado_ia',
                    'creado_en' => $ahora,
                    'actualizado_en' => $ahora,
                ]);

                foreach ($ids as $mensajeId) {
                    DB::table('mensajero_evidencias_acontecimientos')->insert([
                        'acontecimiento_id' => $nuevo,
                        'mensaje_whatsapp_id' => $mensajeId,
                        'vinculo' => 'sustento_ia',
                        'creado_en' => $ahora,
                    ]);
                }

                return (int) $nuevo;
            }, 3);
        } catch (QueryException $error) {
            if (! in_array((int) ($error->errorInfo[1] ?? 0), [2601, 2627], true)) {
                throw $error;
            }
            $id = DB::table('mensajero_acontecimientos_operativos')
                ->where('analisis_ia_id', $analisisId)->value('id');
        }

        if ($id !== null) {
            $this->vinculador->alCrear((int) $id);
        }

        return $id === null ? null : (int) $id;
    }
}
