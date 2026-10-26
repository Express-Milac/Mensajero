<?php

namespace App\Services\Operacion;

use DomainException;
use Illuminate\Support\Facades\DB;

final class RevisarAcontecimientoOperativo
{
    public function ejecutar(int $id, string $estadoNuevo, string $actorTipo, ?int $actorId): void
    {
        if (! in_array($estadoNuevo, ['validado', 'descartado'], true)) {
            throw new DomainException('Resultado de revisión no permitido.');
        }

        DB::transaction(function () use ($id, $estadoNuevo, $actorTipo, $actorId): void {
            $registro = DB::table('mensajero_acontecimientos_operativos')
                ->where('id', $id)->lockForUpdate()->first();

            if ($registro === null
                || ! in_array((int) $registro->chat_monitoreado_id, (array) config('mensajero.ia.grupos_autorizados', []), true)
                || ! DB::table('mensajero_chats_monitoreados')
                    ->where('id', $registro->chat_monitoreado_id)
                    ->where('tipo_chat', 'grupo')->where('es_activo', true)
                    ->where('es_monitoreado', true)->where('es_visible_tablero', true)->exists()) {
                throw new DomainException('Acontecimiento no disponible en un grupo autorizado.');
            }

            if ($registro->estado !== 'pendiente_revision') {
                throw new DomainException('El acontecimiento ya fue revisado; no se puede resolver dos veces.');
            }

            DB::table('mensajero_acontecimientos_operativos')->where('id', $id)->update([
                'estado' => $estadoNuevo,
                'actualizado_en' => now('UTC')->format('Y-m-d H:i:s.u'),
            ]);

            DB::table('mensajero_auditoria_acontecimientos')->insert([
                'acontecimiento_id' => $id,
                'estado_anterior' => $registro->estado,
                'estado_nuevo' => $estadoNuevo,
                'actor_tipo' => $actorTipo,
                'actor_id' => $actorId,
                'registrado_en' => now('UTC')->format('Y-m-d H:i:s.u'),
            ]);
        }, 3);
    }
}
