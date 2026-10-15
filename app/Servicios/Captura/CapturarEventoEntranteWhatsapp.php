<?php

namespace App\Servicios\Captura;

use App\Datos\WhatsApp\EventoEntranteWhatsapp;
use App\Datos\WhatsApp\ResultadoCaptura;
use App\Enumeraciones\DisposicionEntrada;
use App\Modelos\ConversacionMonitoreada;
use App\Modelos\EventoCrudoWhatsapp;
use App\Modelos\MensajeBandejaSalida;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class CapturarEventoEntranteWhatsapp
{
    public function __construct(
        private readonly FiltroMensajeEntrante $filtro,
    ) {
    }

    public function manejar(EventoEntranteWhatsapp $evento): ResultadoCaptura
    {
        $claveDeduplicacion = $evento->claveDeduplicacion();

        try {
            return DB::transaction(function () use ($evento, $claveDeduplicacion): ResultadoCaptura {
                $conversacion = ConversacionMonitoreada::query()
                    ->where('conexion_id', $evento->conexionId)
                    ->where('id_conversacion_proveedor', $evento->conversacionId)
                    ->lockForUpdate()
                    ->first();

                $disposicion = $this->filtro->decidir($conversacion, $evento->tipoMensaje);

                if ($disposicion !== DisposicionEntrada::Aceptar) {
                    return ResultadoCaptura::ignorado($disposicion);
                }

                $existente = EventoCrudoWhatsapp::query()
                    ->where('conexion_id', $evento->conexionId)
                    ->where('clave_deduplicacion', $claveDeduplicacion)
                    ->first();

                if ($existente !== null) {
                    return ResultadoCaptura::duplicado((int) $existente->getKey());
                }

                $crudo = EventoCrudoWhatsapp::query()->create([
                    'conexion_id' => $evento->conexionId,
                    'conversacion_monitoreada_id' => $conversacion->getKey(),
                    'id_evento_externo' => $evento->eventoExternoId,
                    'clave_deduplicacion' => $claveDeduplicacion,
                    'tipo_evento' => $evento->tipoEvento,
                    'contenido_crudo' => $evento->contenidoCrudo,
                    'hash_contenido_sha256' => $evento->hashContenidoSha256(),
                    'version_contrato' => $evento->versionContrato,
                    'estado_procesamiento' => 'capturado',
                    'intentos' => 0,
                    'recibido_en' => $evento->recibidoEn,
                ]);

                MensajeBandejaSalida::query()->create([
                    'tema' => 'whatsapp.crudo.aceptado',
                    'tipo_entidad' => 'evento_crudo_whatsapp',
                    'entidad_id' => $crudo->getKey(),
                    'clave_deduplicacion' => $claveDeduplicacion,
                    'contenido' => json_encode([
                        'evento_crudo_id' => $crudo->getKey(),
                        'conexion_id' => $evento->conexionId,
                        'conversacion_monitoreada_id' => $conversacion->getKey(),
                    ], JSON_THROW_ON_ERROR),
                    'intentos' => 0,
                    'disponible_en' => now('UTC'),
                ]);

                return ResultadoCaptura::aceptado((int) $crudo->getKey());
            }, 3);
        } catch (QueryException $excepcion) {
            if (! $this->esClaveDuplicadaSqlServer($excepcion)) {
                throw $excepcion;
            }

            $existente = EventoCrudoWhatsapp::query()
                ->where('conexion_id', $evento->conexionId)
                ->where('clave_deduplicacion', $claveDeduplicacion)
                ->first();

            if ($existente === null) {
                throw $excepcion;
            }

            return ResultadoCaptura::duplicado((int) $existente->getKey());
        }
    }

    private function esClaveDuplicadaSqlServer(QueryException $excepcion): bool
    {
        $codigoControlador = (int) ($excepcion->errorInfo[1] ?? 0);

        return in_array($codigoControlador, [2601, 2627], true);
    }
}
