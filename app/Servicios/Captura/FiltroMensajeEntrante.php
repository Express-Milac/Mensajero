<?php

namespace App\Servicios\Captura;

use App\Enumeraciones\DisposicionEntrada;
use App\Modelos\ConversacionMonitoreada;

final class FiltroMensajeEntrante
{
    public function decidir(?ConversacionMonitoreada $conversacion, string $tipoMensaje): DisposicionEntrada
    {
        $tipo = mb_strtolower(trim($tipoMensaje));

        if (in_array($tipo, ['adhesivo', 'sticker'], true)) {
            return DisposicionEntrada::IgnorarAdhesivo;
        }

        if ($conversacion === null) {
            return DisposicionEntrada::IgnorarConversacionDesconocida;
        }

        if (! $conversacion->esta_activa) {
            return DisposicionEntrada::IgnorarConversacionInactiva;
        }

        if (! $conversacion->esta_monitoreada) {
            return DisposicionEntrada::IgnorarNoMonitoreada;
        }

        return DisposicionEntrada::Aceptar;
    }
}
