<?php

namespace App\Enums;

/** Valores estables de contrato; equivalentes a los enums de PHP 8. */
final class DisposicionEntrada
{
    public const Aceptar = 'aceptar';
    public const IgnorarPegatina = 'ignorar_pegatina';
    public const IgnorarChatDesconocido = 'ignorar_chat_desconocido';
    public const IgnorarNoMonitoreado = 'ignorar_no_monitoreado';
    public const IgnorarInactivo = 'ignorar_inactivo';

    private function __construct() {}
}
