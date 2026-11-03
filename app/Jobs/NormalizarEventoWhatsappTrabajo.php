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

    /** Nunca publicar trabajos de Gemini desde una recuperación local histórica. */
    public bool $soloLocal = false;

    public function __construct(int $eventoCrudoId, bool $soloLocal = false)
    {
        $this->eventoCrudoId = $eventoCrudoId;
        $this->soloLocal = $soloLocal;
    }

    public function handle(NormalizarEventoWhatsapp $normalizador): void
    {
        $normalizador->ejecutar($this->eventoCrudoId, $this->soloLocal);
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [5, 30, 120, 300];
    }
}
