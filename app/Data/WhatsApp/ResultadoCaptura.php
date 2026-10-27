<?php

namespace App\Data\WhatsApp;

use App\Enums\DisposicionEntrada;

final class ResultadoCaptura
{
    public string $estado;
    /** @var string Uno de los valores de DisposicionEntrada */
    public string $disposicion;
    public ?int $eventoCrudoId;

    private function __construct(string $estado, string $disposicion, ?int $eventoCrudoId = null)
    {
        $this->estado = $estado;
        $this->disposicion = $disposicion;
        $this->eventoCrudoId = $eventoCrudoId;
    }

    public static function aceptado(int $eventoCrudoId): self
    {
        return new self('aceptado', DisposicionEntrada::Aceptar, $eventoCrudoId);
    }

    public static function duplicado(int $eventoCrudoId): self
    {
        return new self('duplicado', DisposicionEntrada::Aceptar, $eventoCrudoId);
    }

    public static function ignorado(string $disposicion): self
    {
        return new self('ignorado', $disposicion);
    }
}
