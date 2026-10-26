<?php

namespace App\Jobs;

use App\Services\IA\ProcesarLoteGemini;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Solo cola manual de desarrollo; no está registrado en el programador.
 * Un timeout tras contactar Google podría haberse facturado: NO reintentar.
 */
final class AnalizarLoteGeminiTrabajo implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 1;
    public int $timeout = 45;

    public function __construct(public readonly int $loteId)
    {
        $this->onConnection('redis');
        // Cola separada: el trabajador del piloto NO consume estos trabajos.
        $this->onQueue('mensajero_ia_lotes');
    }

    public function handle(ProcesarLoteGemini $procesador): void
    {
        $procesador->ejecutar($this->loteId);
    }

    public function failed(?Throwable $error): void
    {
        try {
            if (! Schema::hasTable('mensajero_lotes_gemini')) {
                return;
            }

            // Solo los intentos iniciados pueden considerarse inciertos.
            DB::table('mensajero_lotes_gemini')->where('id', $this->loteId)
                ->where('estado', 'procesando')->update([
                    'estado' => 'requiere_revision',
                    'error_tipo' => $error === null
                        ? 'ErrorDesconocido' : substr(class_basename($error), 0, 120),
                ]);
        } catch (Throwable $registroError) {
            Log::warning('Fallo del lote Gemini sin reintento; revisar SQL manualmente.', [
                'lote_id' => $this->loteId,
                'tipo_error' => class_basename($registroError),
            ]);
        }
    }
}
