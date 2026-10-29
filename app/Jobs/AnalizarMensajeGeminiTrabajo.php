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

    public int $mensajeId;

    public function __construct(int $mensajeId)
    {
        $this->mensajeId = $mensajeId;
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
        $nuevo = self::clasificarEstado($estado);

        DB::table('mensajero_control_publicaciones_gemini')
            ->where('mensaje_whatsapp_id', $this->mensajeId)
            ->whereIn('estado', ['reservado', 'publicado'])
            ->update([
                'estado' => $nuevo,
                // Se registra la causa sin contenidos, teléfonos ni credenciales.
                'error_tipo' => in_array($nuevo, ['omitido', 'requiere_revision'], true)
                    ? $estado : null,
            ]);
    }

    /**
     * Solo descartes explícitamente ANTERIORES al servicio externo son omitidos.
     * Cualquier estado desconocido o posterior a Google exige revisión.
     */
    public static function clasificarEstado(string $resultado): string
    {
        if (in_array($resultado, ['identificado_ia', 'pendiente_revision', 'sin_evento', 'duplicado'], true)) {
            return 'finalizado';
        }
        if (in_array($resultado, ['no_elegible', 'sin_texto'], true)) {
            return 'omitido';
        }
        return 'requiere_revision';
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
