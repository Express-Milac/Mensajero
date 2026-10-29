<?php

namespace App\Jobs;

use App\Services\Normalizacion\NormalizarEventoWhatsapp;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

final class NormalizarEventoWhatsappTrabajo implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 60;

    public int $eventoCrudoId;

    public function __construct(int $eventoCrudoId)
    {
        $this->eventoCrudoId = $eventoCrudoId;
    }

    public function handle(NormalizarEventoWhatsapp $normalizador): void
    {
        $normalizador->ejecutar($this->eventoCrudoId);
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [5, 30, 120, 300];
    }
}
