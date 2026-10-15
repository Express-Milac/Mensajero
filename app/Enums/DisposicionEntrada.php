<?php

namespace App\Enums;

enum DisposicionEntrada: string
{
    case Aceptar = 'aceptar';
    case IgnorarPegatina = 'ignorar_pegatina';
    case IgnorarChatDesconocido = 'ignorar_chat_desconocido';
    case IgnorarNoMonitoreado = 'ignorar_no_monitoreado';
    case IgnorarInactivo = 'ignorar_inactivo';
}
