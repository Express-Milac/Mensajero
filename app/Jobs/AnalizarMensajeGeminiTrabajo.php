<?php

namespace App\Jobs;

use App\Services\IA\ProcesarAnalisisGemini;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class AnalizarMensajeGeminiTrabajo implements ShouldQueue
{
    use Dispatchable, Queueable;

    // Una respuesta incierta de Google NO se reenvía automáticamente:
    // podría haber sido facturada antes de que fallara la persistencia SQL.
    public int $tries = 1;

    public int $timeout = 45;

    public function __construct(public readonly int $mensajeId)
    {
        $this->onConnection('redis');
        $this->onQueue((string) config('mensajero.ia.cola', 'mensajero_ia'));
    }

    public function handle(ProcesarAnalisisGemini $procesar): void
    {
        $estado = $procesar->ejecutar($this->mensajeId);

        if (! Schema::hasTable('mensajero_control_publicaciones_gemini')) {
            return;
        }

        // También es seguro si el trabajo se ejecutó antes de confirmar en
        // SQL que la publicación llegó a Redis.
        $nuevo = in_array($estado, ['pendiente_revision', 'sin_evento', 'duplicado'], true)
            ? 'finalizado' : 'omitido';

        DB::table('mensajero_control_publicaciones_gemini')
            ->where('mensaje_whatsapp_id', $this->mensajeId)
            ->whereIn('estado', ['reservado', 'publicado'])
            ->update(['estado' => $nuevo]);
    }

    public function failed(?Throwable $error): void
    {
        try {
            if (! Schema::hasTable('mensajero_control_publicaciones_gemini')) {
                return;
            }

            DB::table('mensajero_control_publicaciones_gemini')
                ->where('mensaje_whatsapp_id', $this->mensajeId)
                ->whereIn('estado', ['reservado', 'publicado'])
                ->update([
                    'estado' => 'requiere_revision',
                    'error_tipo' => $error === null ? 'ErrorDesconocido' : substr(class_basename($error), 0, 120),
                ]);
        } catch (Throwable $registroError) {
            Log::warning('No fue posible registrar fallo de publicación Gemini sin reintento automático.', [
                'mensaje_id' => $this->mensajeId,
                'tipo_error' => class_basename($registroError),
            ]);
        }
    }
}
