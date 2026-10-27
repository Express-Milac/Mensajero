<?php

namespace App\Services\Monitoreo;

use App\Models\AuditoriaMonitoreoChat;
use App\Models\ChatMonitoreado;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Un solo estado funcional por grupo: captura autorizada = visible en tablero.
 * Cada modificación se registra y se realiza atómicamente en SQL Server.
 */
final class CambiarMonitoreoChat
{
    public function ejecutar(
        int $chatId,
        bool $esMonitoreado,
        string $actorTipo,
        ?int $actorId = null,
        ?string $motivo = null) : ChatMonitoreado {
        return DB::transaction(function () use ($chatId, $esMonitoreado, $actorTipo, $actorId, $motivo): ChatMonitoreado {
            $chat = ChatMonitoreado::query()
                ->where('tipo_chat', 'grupo')
                ->lockForUpdate()
                ->findOrFail($chatId);

            if ($esMonitoreado && ! $chat->es_activo) {
                throw new DomainException('No se puede habilitar un grupo inactivo.');
            }

            $anteriorMonitoreo = (bool) $chat->es_monitoreado;
            $anteriorVisibilidad = (bool) $chat->es_visible_tablero;

            // Reparar estados anteriores desalineados al tocar el interruptor,
            // sin cambiar de forma masiva grupos ni registros históricos.
            if ($anteriorMonitoreo === $esMonitoreado && $anteriorVisibilidad === $esMonitoreado) {
                return $chat;
            }

            $chat->es_monitoreado = $esMonitoreado;
            $chat->es_visible_tablero = $esMonitoreado;
            if ($anteriorMonitoreo !== $esMonitoreado) {
                $chat->version_autorizacion = $chat->version_autorizacion + 1;
            }
            $chat->save();

            if ($anteriorMonitoreo !== $esMonitoreado) {
                AuditoriaMonitoreoChat::create([
                    'chat_monitoreado_id' => $chat->id,
                    'version_autorizacion' => $chat->version_autorizacion,
                    'es_monitoreado_anterior' => $anteriorMonitoreo,
                    'es_monitoreado_nuevo' => $esMonitoreado,
                    'actor_tipo' => $actorTipo,
                    'actor_id' => $actorId,
                    'motivo' => $motivo !== null && trim($motivo) !== '' ? trim($motivo) : null,
                ]);
            }

            if ($anteriorVisibilidad !== $esMonitoreado) {
                DB::table('mensajero_auditoria_visibilidad_grupos')->insert([
                    'chat_monitoreado_id' => $chat->id,
                    'visible_anterior' => $anteriorVisibilidad,
                    'visible_nuevo' => $esMonitoreado,
                    'actor_tipo' => $actorTipo,
                    'actor_id' => $actorId,
                    'registrado_en' => now('UTC')->format('Y-m-d H:i:s.u'),
                ]);
            }

            return $chat->fresh();
        }, 3);
    }
}
