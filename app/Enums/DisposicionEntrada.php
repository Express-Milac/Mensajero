<?php

namespace App\Enums;

enum DisposicionEntrada: string
{
    case Aceptar = 'aceptar';
    case IgnorarAdhesivo = 'ignorar_adhesivo';
    case IgnorarConversacionDesconocida = 'ignorar_conversacion_desconocida';
    case IgnorarConversacionInactiva = 'ignorar_conversacion_inactiva';
    case IgnorarNoMonitoreada = 'ignorar_no_monitoreada';
}
