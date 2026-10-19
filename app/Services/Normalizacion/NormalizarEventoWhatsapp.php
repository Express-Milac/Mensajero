<?php

namespace App\Services\Normalizacion;

use App\Models\ArchivoWhatsapp;
use App\Models\ContactoWhatsapp;
use App\Models\EventoCrudoWhatsapp;
use App\Models\MensajeWhatsapp;
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
        return DB::transaction(function () use ($eventoCrudoId): string {
            $evento = EventoCrudoWhatsapp::query()
                ->whereKey($eventoCrudoId)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($evento->estado_procesamiento, ['normalizado', 'pendiente_semantica'], true)) {
                return 'duplicado';
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
    }
}
