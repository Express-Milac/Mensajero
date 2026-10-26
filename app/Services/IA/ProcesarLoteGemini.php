<?php

namespace App\Services\IA;

use App\Models\MensajeWhatsapp;
use DateTimeInterface;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Un solo intento externo por lote. No despacha trabajos ni consulta historiales
 * anteriores a la autorización. Nunca envía Google dentro de una transacción.
 */
final class ProcesarLoteGemini
{
    public function __construct(
        private readonly PoliticaEnvioGemini $politica,
        private readonly PoliticaLotesGemini $lotes,
        private readonly ControlInterpretacionGemini $control,
        private readonly ConstruirDialogoGemini $dialogo,
        private readonly ClienteGeminiLote $cliente,
        private readonly PersistirResultadosLoteGemini $persistencia,
    ) {}

    public function ejecutar(int $loteId): string
    {
        if ($loteId < 1 || ! $this->lotes->habilitada()
            || ! $this->politica->configuracionCompleta()
            || ! $this->control->disponible()
            || ! Schema::hasTable('mensajero_lotes_gemini')
            || ! Schema::hasTable('mensajero_mensajes_lote_gemini')) {
            return 'deshabilitado';
        }

        // Primer paso: lectura y cambio exclusivo de publicado a procesando.
        // Si el proceso muere luego, JAMÁS se repite automáticamente la llamada.
        $mensajes = DB::transaction(function () use ($loteId): ?array {
            $datosLote = DB::table('mensajero_lotes_gemini')->where('id', $loteId)
                ->first(['chat_monitoreado_id']);
            if ($datosLote === null) {
                return null;
            }

            $grupoId = (int) $datosLote->chat_monitoreado_id;
            $grupo = DB::table('mensajero_chats_monitoreados')
                ->where('id', $grupoId)->where('tipo_chat', 'grupo')
                ->where('es_activo', true)->where('es_monitoreado', true)
                ->where('es_visible_tablero', true)->lockForUpdate()->first(['id']);

            $lote = DB::table('mensajero_lotes_gemini')->where('id', $loteId)
                ->lockForUpdate()->first(['id', 'chat_monitoreado_id', 'estado']);

            if ($lote === null || $lote->estado !== 'publicado') {
                return null;
            }

            $ids = DB::table('mensajero_mensajes_lote_gemini')
                ->where('lote_id', $loteId)->orderBy('posicion')
                ->pluck('mensaje_whatsapp_id')
                ->map(static fn ($id): int => (int) $id)->all();

            $fuentes = MensajeWhatsapp::query()->where('chat_monitoreado_id', $grupoId)
                ->whereIn('id', $ids)->get([
                    'id', 'chat_monitoreado_id', 'contacto_remitente_id',
                    'mensaje_citado_id', 'estado_captura', 'tipo_mensaje',
                    'contenido_texto', 'pie_multimedia', 'recibido_en',
                ])->keyBy('id');

            $correcto = $grupo !== null && $ids !== []
                && count($ids) <= AgruparMensajesGemini::MAXIMO_MENSAJES
                && count(array_unique($ids)) === count($ids)
                && $fuentes->count() === count($ids);

            $orden = [];
            foreach ($ids as $id) {
                $mensaje = $fuentes->get($id);
                if ($mensaje === null || ! $this->permitido($grupoId, $mensaje)) {
                    $correcto = false;
                    break;
                }
                $orden[] = $mensaje;
            }

            if (! $correcto || count($orden) !== count($ids)) {
                DB::table('mensajero_lotes_gemini')->where('id', $loteId)
                    ->where('estado', 'publicado')
                    ->update(['estado' => 'omitido', 'error_tipo' => 'SinAutorizacionOVigencia']);
                return null;
            }

            if (DB::table('mensajero_control_publicaciones_gemini')
                ->whereIn('mensaje_whatsapp_id', $ids)->exists()
                || DB::table('mensajero_analisis_operativos_ia')
                    ->whereIn('mensaje_whatsapp_id', $ids)->exists()) {
                DB::table('mensajero_lotes_gemini')->where('id', $loteId)
                    ->where('estado', 'publicado')
                    ->update(['estado' => 'requiere_revision', 'error_tipo' => 'DuplicadoCircuitoIndividual']);
                return null;
            }

            DB::table('mensajero_lotes_gemini')->where('id', $loteId)
                ->where('estado', 'publicado')->update(['estado' => 'procesando']);

            return $orden;
        }, 3);

        if ($mensajes === null) {
            return 'omitido';
        }

        try {
            $contexto = $this->dialogo->ejecutar($mensajes);
            if (count($contexto) !== count($mensajes)) {
                throw new DomainException('El contexto del lote quedó incompleto.');
            }

            // Revalidación inmediatamente anterior a la solicitud pagada.
            // No se usa la bandera del piloto de mensaje individual.
            $grupoId = (int) $mensajes[0]->chat_monitoreado_id;
            if (! $this->lotes->habilitada() || ! $this->politica->configuracionCompleta()) {
                $this->marcar($loteId, 'omitido', 'AutorizacionRevocada');
                return 'omitido';
            }
            foreach ($mensajes as $mensaje) {
                if (! $this->permitido($grupoId, $mensaje)) {
                    $this->marcar($loteId, 'omitido', 'AutorizacionRevocada');
                    return 'omitido';
                }
            }

            // Solicitud externa exactamente una vez por ejecución exclusiva.
            $respuesta = $this->cliente->analizar($contexto);
            return $this->persistencia->guardar($loteId, $respuesta);
        } catch (Throwable $error) {
            // Tras reclamar el lote no sabemos si Google facturó.
            $this->marcar($loteId, 'requiere_revision', substr(class_basename($error), 0, 120));
            Log::warning('Lote Gemini requiere revisión; no habrá reintento automático.', [
                'lote_id' => $loteId,
                'tipo_error' => class_basename($error),
            ]);
            throw $error;
        }
    }

    private function permitido(int $grupoId, MensajeWhatsapp $mensaje): bool
    {
        $fecha = $mensaje->recibido_en;
        $texto = $mensaje->tipo_mensaje === 'texto'
            ? $mensaje->contenido_texto : $mensaje->pie_multimedia;

        return $mensaje->estado_captura === 'normalizado'
            && $fecha instanceof DateTimeInterface
            && trim((string) $texto) !== ''
            && in_array($mensaje->tipo_mensaje, ['texto', 'foto', 'video', 'documento'], true)
            && $this->politica->autoriza($grupoId, $fecha)
            && $this->lotes->posteriorAlCorte($fecha)
            && $this->control->permite($grupoId, $fecha);
    }

    private function marcar(int $loteId, string $estado, string $razon): void
    {
        DB::table('mensajero_lotes_gemini')->where('id', $loteId)
            ->where('estado', 'procesando')
            ->update(['estado' => $estado, 'error_tipo' => $razon]);
    }
}
