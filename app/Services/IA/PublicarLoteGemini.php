<?php

namespace App\Services\IA;

use App\Jobs\AnalizarLoteGeminiTrabajo;
use App\Models\MensajeWhatsapp;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * Reserva y publica SOLO los mensajes seleccionados explícitamente para un lote.
 *
 * Sin planificador, ruta ni tarea programada: no realiza envíos automáticamente.
 * El estado "publicado" se confirma ANTES de poner el trabajo en Redis, ya que
 * el consumidor puede ejecutarse de inmediato. Ante cualquier incertidumbre,
 * marca requiere_revision y nunca repite la publicación sin intervención manual.
 */
final class PublicarLoteGemini
{
    public function __construct(
        private readonly PoliticaEnvioGemini $politica,
        private readonly PoliticaLotesGemini $lotes,
        private readonly ControlInterpretacionGemini $control,
        private readonly PresupuestoPublicacionesGemini $presupuesto,
    ) {}

    /**
     * @param array<int, int> $mensajeIds Identificadores explícitos de un único grupo.
     * @return string deshabilitado | no_elegible | registrado | limite_diario | publicado
     */
    public function publicar(int $grupoId, array $mensajeIds): string
    {
        if (! $this->lotes->habilitada() || ! $this->politica->configuracionCompleta()
            || ! $this->control->disponible()
            || ! Schema::hasTable('mensajero_lotes_gemini')
            || ! Schema::hasTable('mensajero_mensajes_lote_gemini')
            || ! Schema::hasTable('mensajero_control_publicaciones_gemini')
            || ! Schema::hasTable('mensajero_analisis_operativos_ia')) {
            return 'deshabilitado';
        }

        if ($grupoId < 1 || $mensajeIds === []
            || count($mensajeIds) > AgruparMensajesGemini::MAXIMO_MENSAJES
            || count(array_unique($mensajeIds)) !== count($mensajeIds)) {
            return 'no_elegible';
        }

        foreach ($mensajeIds as $id) {
            if (! is_int($id) || $id < 1) {
                return 'no_elegible';
            }
        }

        // SQL es la fuente de verdad. El bloqueo del grupo serializa reservas
        // de lotes e individuales contra el MISMO presupuesto diario.
        $reservado = DB::transaction(function () use ($grupoId, $mensajeIds): int|string {
            $grupo = DB::table('mensajero_chats_monitoreados')
                ->where('id', $grupoId)
                ->where('tipo_chat', 'grupo')
                ->where('es_activo', true)
                ->where('es_monitoreado', true)
                ->where('es_visible_tablero', true)
                ->lockForUpdate()
                ->first(['id']);

            if ($grupo === null) {
                return 'no_elegible';
            }

            $mensajes = MensajeWhatsapp::query()
                ->where('chat_monitoreado_id', $grupoId)
                ->whereIn('id', $mensajeIds)
                ->orderBy('recibido_en')
                ->orderBy('id')
                ->get([
                    'id', 'chat_monitoreado_id', 'tipo_mensaje', 'estado_captura',
                    'contenido_texto', 'pie_multimedia', 'recibido_en',
                ]);

            if ($mensajes->count() !== count($mensajeIds)) {
                return 'no_elegible';
            }

            $orden = [];
            foreach ($mensajes as $mensaje) {
                $fecha = $mensaje->recibido_en;
                $texto = $mensaje->tipo_mensaje === 'texto'
                    ? $mensaje->contenido_texto : $mensaje->pie_multimedia;

                if ($mensaje->estado_captura !== 'normalizado'
                    || ! $fecha instanceof DateTimeInterface
                    || ! in_array($mensaje->tipo_mensaje, ['texto', 'foto', 'video', 'documento'], true)
                    || trim((string) $texto) === ''
                    || ! $this->politica->autoriza($grupoId, $fecha)
                    || ! $this->lotes->posteriorAlCorte($fecha)
                    || ! $this->control->permite($grupoId, $fecha, true)) {
                    return 'no_elegible';
                }
                $orden[] = (int) $mensaje->id;
            }

            $hash = hash('sha256', implode(',', $orden));
            if (DB::table('mensajero_lotes_gemini')
                ->where('chat_monitoreado_id', $grupoId)
                ->where('hash_mensajes', $hash)->exists()) {
                return 'registrado';
            }

            if (DB::table('mensajero_mensajes_lote_gemini')
                ->whereIn('mensaje_whatsapp_id', $orden)->exists()
                || DB::table('mensajero_control_publicaciones_gemini')
                    ->whereIn('mensaje_whatsapp_id', $orden)->exists()
                || DB::table('mensajero_analisis_operativos_ia')
                    ->whereIn('mensaje_whatsapp_id', $orden)->exists()) {
                return 'no_elegible';
            }

            if (! $this->presupuesto->permiteReserva($grupoId)) {
                return 'limite_diario';
            }

            $ahora = now('UTC')->format('Y-m-d H:i:s.u');
            $loteId = (int) DB::table('mensajero_lotes_gemini')->insertGetId([
                'chat_monitoreado_id' => $grupoId,
                'hash_mensajes' => $hash,
                'estado' => 'reservado',
                'origen' => 'manual',
                'reservado_en' => $ahora,
            ]);

            foreach ($orden as $posicion => $mensajeId) {
                DB::table('mensajero_mensajes_lote_gemini')->insert([
                    'lote_id' => $loteId,
                    'mensaje_whatsapp_id' => $mensajeId,
                    'posicion' => $posicion + 1,
                ]);
            }

            return $loteId;
        }, 3);

        if (! is_int($reservado)) {
            return $reservado;
        }

        // No existe ningún despacho dentro de la transacción SQL. Un trabajo
        // puede llegar a Redis y ser ejecutado ANTES de volver a esta función.
        // Por ello se marca "publicado" antes del envío, no después.
        try {
            $actualizado = DB::table('mensajero_lotes_gemini')
                ->where('id', $reservado)
                ->where('estado', 'reservado')
                ->update([
                    'estado' => 'publicado',
                    'publicado_en' => now('UTC')->format('Y-m-d H:i:s.u'),
                ]);

            if ($actualizado !== 1) {
                throw new RuntimeException('La reserva del lote cambió antes de publicarse.');
            }

            AnalizarLoteGeminiTrabajo::dispatch($reservado);

            return 'publicado';
        } catch (Throwable $error) {
            // Redis pudo recibir el trabajo incluso si devolvió un error.
            // Los estados procesando/finalizado NO se sobrescriben.
            DB::table('mensajero_lotes_gemini')
                ->where('id', $reservado)
                ->whereIn('estado', ['reservado', 'publicado'])
                ->update([
                    'estado' => 'requiere_revision',
                    'error_tipo' => substr(class_basename($error), 0, 120),
                ]);

            Log::warning('Publicación de lote incierta: revisión manual obligatoria.', [
                'lote_id' => $reservado,
                'tipo_error' => class_basename($error),
            ]);

            throw new RuntimeException(
                'Publicación del lote incierta; no volver a encolar automáticamente.',
                0,
                $error,
            );
        }
    }
}
