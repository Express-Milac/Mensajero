<?php

namespace App\Services\Grupos;

use App\Models\ChatMonitoreado;
use Illuminate\Support\Facades\DB;

final class CambiarVisibilidadTablero
{
    public function ejecutar(int $chatId, bool $visible, string $tipoActor, ?int $actorId = null): ChatMonitoreado
    {
        return DB::transaction(function () use ($chatId, $visible, $tipoActor, $actorId): ChatMonitoreado {
            $grupo = ChatMonitoreado::query()
                ->where('tipo_chat', 'grupo')
                ->lockForUpdate()
                ->findOrFail($chatId);

            if ($grupo->es_visible_tablero === $visible) {
                return $grupo;
            }

            $anterior = $grupo->es_visible_tablero;
            $grupo->es_visible_tablero = $visible;
            $grupo->save();

            DB::table('mensajero_auditoria_visibilidad_grupos')->insert([
                'chat_monitoreado_id' => $grupo->id,
                'visible_anterior' => $anterior,
                'visible_nuevo' => $visible,
                'actor_tipo' => $tipoActor,
                'actor_id' => $actorId,
                'registrado_en' => now('UTC')->format('Y-m-d H:i:s.u'),
            ]);

            return $grupo;
        });
    }
}
