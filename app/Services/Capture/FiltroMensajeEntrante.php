<?php

namespace App\Services\Capture;

use App\Enums\DisposicionEntrada;
use App\Models\ChatMonitoreado;

final class FiltroMensajeEntrante
{
    public function decidir(?ChatMonitoreado $chat, string $tipoMensaje): DisposicionEntrada
    {
        if (in_array(mb_strtolower($tipoMensaje), ['sticker', 'pegatina'], true)) {
            return DisposicionEntrada::IgnorarPegatina;
        }

        if ($chat === null) {
            return DisposicionEntrada::IgnorarChatDesconocido;
        }

        if (! $chat->es_activo) {
            return DisposicionEntrada::IgnorarInactivo;
        }

        if (! $chat->es_monitoreado) {
            return DisposicionEntrada::IgnorarNoMonitoreado;
        }

        return DisposicionEntrada::Aceptar;
    }
}
