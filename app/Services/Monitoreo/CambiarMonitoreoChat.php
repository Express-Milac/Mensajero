<?php

namespace App\Services\Monitoreo;

use App\Models\AuditoriaMonitoreoChat;
use App\Models\ChatMonitoreado;
use DomainException;
use Illuminate\Support\Facades\DB;

final class CambiarMonitoreoChat
{
    public function ejecutar(
        int $chatId,
        bool $esMonitoreado,
        string $actorTipo,
        ?int $actorId = null,
        ?string $motivo = null,
    ): ChatMonitoreado {
        return DB::transaction(function () use ($chatId, $esMonitoreado, $actorTipo, $actorId, $motivo) {
            $chat = ChatMonitoreado::query()->lockForUpdate()->findOrFail($chatId);

            if ($esMonitoreado && ! $chat->es_activo) {
                throw new DomainException('No se puede activar el monitoreo de un grupo inactivo.');
            }

            if ($chat->es_monitoreado === $esMonitoreado) {
                return $chat;
            }

            $estadoAnterior = $chat->es_monitoreado;
            $chat->es_monitoreado = $esMonitoreado;
            $chat->version_autorizacion = $chat->version_autorizacion + 1;
            $chat->save();

            AuditoriaMonitoreoChat::create([
                'chat_monitoreado_id' => $chat->id,
                'version_autorizacion' => $chat->version_autorizacion,
                'es_monitoreado_anterior' => $estadoAnterior,
                'es_monitoreado_nuevo' => $esMonitoreado,
                'actor_tipo' => $actorTipo,
                'actor_id' => $actorId,
                'motivo' => $motivo !== null && trim($motivo) !== '' ? trim($motivo) : null,
            ]);

            return $chat->fresh();
        });
    }
}
