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
    private CambiarMonitoreoChat $cambiarMonitoreo;

    public function __construct(CambiarMonitoreoChat $cambiarMonitoreo)
    {
        $this->cambiarMonitoreo = $cambiarMonitoreo;
    }

    public function ejecutar(int $chatId, bool $visible, string $tipoActor, ?int $actorId = null): ChatMonitoreado
    {
        return $this->cambiarMonitoreo->ejecutar($chatId, $visible, $tipoActor, $actorId);
    }
}
