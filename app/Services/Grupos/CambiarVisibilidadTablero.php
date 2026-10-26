<?php

namespace App\Services\Grupos;

use App\Models\ChatMonitoreado;
use App\Services\Monitoreo\CambiarMonitoreoChat;

/**
 * Compatibilidad con el servicio anterior. Ya no se admite cambiar solo
 * la visibilidad: siempre se sincroniza la autorización de captura.
 *
 * @deprecated Utilizar CambiarMonitoreoChat para gestionar el grupo.
 */
final class CambiarVisibilidadTablero
{
    public function __construct(private readonly CambiarMonitoreoChat $cambiarMonitoreo) {}

    public function ejecutar(int $chatId, bool $visible, string $tipoActor, ?int $actorId = null): ChatMonitoreado
    {
        return $this->cambiarMonitoreo->ejecutar($chatId, $visible, $tipoActor, $actorId);
    }
}
