<?php

namespace App\Services\Normalizacion;

use App\Models\ArchivoWhatsapp;
use App\Models\ChatMonitoreado;
use App\Models\ContactoWhatsapp;
use App\Models\EventoCrudoWhatsapp;
use App\Models\MensajeWhatsapp;
use App\Jobs\AnalizarMensajeGeminiTrabajo;
use App\Services\IA\PoliticaEnvioGemini;
use App\Services\Operacion\VincularEvidenciasOperativas;
use Illuminate\Support\Facades\Log;
use Throwable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Idempotente incluso si Redis reentrega una tarea después de una caída.
 * Nunca sustituye la evidencia original ni convierte ediciones en mensajes nuevos.
 */
final class NormalizarEventoWhatsapp
{
    public function __construct(private readonly ExtraerContenidoWhatsapp $extraer) {}

    public function ejecutar(int $eventoCrudoId): string
    {
        $resultado = DB::transaction(function () use ($eventoCrudoId): string {
            $evento = EventoCrudoWhatsapp::query()
                ->whereKey($eventoCrudoId)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($evento->estado_procesamiento, ['normalizado', 'pendiente_semantica'], true)) {
                return 'duplicado';
            }

            // El grupo pudo ser desautorizado mientras la tarea esperaba en Redis.
            // Verificar en la misma transacción que realiza la proyección y
            // bloquear el grupo durante su ejecución. La evidencia original
            // permanece intacta si la autorización fue retirada.
            $grupo = ChatMonitoreado::query()
                ->whereKey($evento->chat_monitoreado_id)
                ->where('conexion_id', $evento->conexion_id)
                ->where('tipo_chat', 'grupo')
                ->where('es_monitoreado', true)
                ->where('es_visible_tablero', true)
                ->where('es_activo', true)
                ->lockForUpdate()
                ->first();

            if ($grupo === null) {
                throw new RuntimeException('Grupo no autorizado para normalización: revisar antes de reintentar.');
            }

            if ($evento->tipo_evento !== 'mensaje') {
                $evento->estado_procesamiento = 'pendiente_semantica';
                $evento->mensaje_error = null;
                $evento->save();

                return 'pendiente_semantica';
            }

            $datos = $this->extraer->ejecutar($evento->contenido);
            $idProveedor = $evento->id_evento_externo;

            if (! is_string($idProveedor) || $idProveedor === '') {
                // Identidad interna estable: no se presenta como ID de WhatsApp.
                $idProveedor = 'sin_id_evento_crudo_'.(int) $evento->id;
            }

            $contactoId = null;
            $remitente = $evento->id_remitente_proveedor;

            if (is_string($remitente) && $remitente !== '') {
                $contacto = ContactoWhatsapp::query()
                    ->where('conexion_id', $evento->conexion_id)
                    ->where('id_contacto_proveedor', $remitente)
                    ->lockForUpdate()
                    ->first();

                if ($contacto === null) {
                    $contacto = ContactoWhatsapp::query()->create([
                        'conexion_id' => $evento->conexion_id,
                        'id_contacto_proveedor' => $remitente,
                        'visto_primera_vez_en' => $evento->recibido_en,
                        'visto_ultima_vez_en' => $evento->recibido_en,
                    ]);
                } elseif ($contacto->visto_ultima_vez_en === null
                    || $contacto->visto_ultima_vez_en->lessThan($evento->recibido_en)) {
                    $contacto->visto_ultima_vez_en = $evento->recibido_en;
                    $contacto->save();
                }

                $contactoId = $contacto->id;
            }

            $mensaje = MensajeWhatsapp::query()
                ->where('conexion_id', $evento->conexion_id)
                ->where('chat_monitoreado_id', $evento->chat_monitoreado_id)
                ->where('id_mensaje_proveedor', $idProveedor)
                ->lockForUpdate()
                ->first();

            if ($mensaje !== null && (int) $mensaje->evento_crudo_id !== (int) $evento->id) {
                throw new RuntimeException('Conflicto de identidad de mensaje: requiere revisión manual.');
            }

            if ($mensaje === null) {
                $citadoId = null;
                $idCitado = $evento->id_mensaje_citado_proveedor;

                if (is_string($idCitado) && $idCitado !== '') {
                    $citadoId = MensajeWhatsapp::query()
                        ->where('conexion_id', $evento->conexion_id)
                        ->where('chat_monitoreado_id', $evento->chat_monitoreado_id)
                        ->where('id_mensaje_proveedor', $idCitado)
                        ->value('id');
                }

                $tipo = $evento->tipo_mensaje ?: 'otro';
                $requiereMultimedia = in_array($tipo, ['foto', 'video', 'audio', 'documento'], true);
                $archivo = ArchivoWhatsapp::query()->where('evento_crudo_id', $evento->id)->first();

                $mensaje = MensajeWhatsapp::query()->create([
                    'conexion_id' => $evento->conexion_id,
                    'chat_monitoreado_id' => $evento->chat_monitoreado_id,
                    'contacto_remitente_id' => $contactoId,
                    'evento_crudo_id' => $evento->id,
                    'id_mensaje_proveedor' => $idProveedor,
                    'tipo_mensaje' => $tipo,
                    'contenido_texto' => $datos['texto'],
                    'pie_multimedia' => $datos['pie_multimedia'],
                    'enviado_en' => $datos['enviado_en'],
                    'recibido_en' => $evento->recibido_en,
                    'procesado_en' => now('UTC'),
                    'id_mensaje_citado_proveedor' => $idCitado,
                    'mensaje_citado_id' => $citadoId,
                    'estado_captura' => 'normalizado',
                    'estado_multimedia' => $archivo !== null && $archivo->estado === 'verificado'
                        ? 'verificado'
                        : ($requiereMultimedia ? 'pendiente' : 'no_requerido'),
                    'estado_ia' => 'no_elegible',
                ]);
            }

            // Si una respuesta llegó antes de su mensaje citado, enlazarla al normalizar el original.
            MensajeWhatsapp::query()
                ->where('conexion_id', $evento->conexion_id)
                ->where('chat_monitoreado_id', $evento->chat_monitoreado_id)
                ->where('id_mensaje_citado_proveedor', $idProveedor)
                ->whereNull('mensaje_citado_id')
                ->where('id', '!=', $mensaje->id)
                ->update(['mensaje_citado_id' => $mensaje->id]);

            $evento->estado_procesamiento = 'normalizado';
            $evento->procesado_en = now('UTC');
            $evento->mensaje_error = null;
            $evento->save();

            return 'normalizado';
        }, 3);

        // Operación desacoplada: ninguna falla de IA o de sugerencias de
        // adjuntos revierte el COMMIT de los mensajes originales.
        if ($resultado === 'normalizado') {
            try {
                $proyeccion = MensajeWhatsapp::query()
                    ->where('evento_crudo_id', $eventoCrudoId)
                    ->first(['id', 'chat_monitoreado_id', 'tipo_mensaje', 'contenido_texto',
                        'pie_multimedia', 'recibido_en']);

                if ($proyeccion !== null) {
                    // Adjuntos de un acontecimiento previamente registrado
                    // (si son inequívocos; solo una vinculación posible).
                    app(VincularEvidenciasOperativas::class)->alNormalizar((int) $proyeccion->id);

                    $textoAnalizable = $proyeccion->tipo_mensaje === 'texto'
                        ? trim((string) $proyeccion->contenido_texto) !== ''
                        : (in_array($proyeccion->tipo_mensaje, ['foto','video','documento'], true)
                            && trim((string) $proyeccion->pie_multimedia) !== '');

                    if ($textoAnalizable
                        && config('mensajero.ia.ejecucion_automatica') === true
                        && $proyeccion->recibido_en !== null
                        && app(PoliticaEnvioGemini::class)->autoriza(
                            (int) $proyeccion->chat_monitoreado_id, $proyeccion->recibido_en
                        )) {
                        AnalizarMensajeGeminiTrabajo::dispatch((int) $proyeccion->id);
                    }
                }
            } catch (Throwable $error) {
                Log::warning('Falló una tarea operativa posterior a normalización; evidencia conservada.', [
                    'evento_crudo_id' => $eventoCrudoId,
                    'tipo_error' => get_class($error),
                ]);
            }
        }

        return $resultado;
    }
}
