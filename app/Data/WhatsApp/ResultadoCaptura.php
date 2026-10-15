<?php

namespace App\Data\WhatsApp;

use App\Enums\DisposicionEntrada;

final readonly class ResultadoCaptura
{
    private function __construct(
        public string $estado,
        public DisposicionEntrada $disposicion,
        public ?int $eventoCrudoId = null,
    ) {
    }

    public static function aceptado(int $eventoCrudoId): self
    {
        return new self('aceptado', DisposicionEntrada::Aceptar, $eventoCrudoId);
    }

    public static function duplicado(int $eventoCrudoId): self
    {
        return new self('duplicado', DisposicionEntrada::Aceptar, $eventoCrudoId);
    }

    public static function ignorado(DisposicionEntrada $disposicion): self
    {
        return new self('ignorado', $disposicion);
    }
}
