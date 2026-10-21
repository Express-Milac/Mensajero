<?php

namespace App\Jobs;

use App\Services\IA\ProcesarAnalisisGemini;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

final class AnalizarMensajeGeminiTrabajo implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 45;

    public function __construct(public readonly int $mensajeId)
    {
        $this->onConnection('redis');
        $this->onQueue((string) config('mensajero.ia.cola', 'mensajero_ia'));
    }

    public function handle(ProcesarAnalisisGemini $procesar): void
    {
        $procesar->ejecutar($this->mensajeId);
    }

    /** @return array<int,int> */
    public function backoff(): array
    {
        return [30, 120, 300];
    }
}
