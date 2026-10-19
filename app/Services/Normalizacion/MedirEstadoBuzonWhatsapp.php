<?php

namespace App\Services\Normalizacion;

use App\Models\MensajeBuzonSalida;

/**
 * Medición de SQL Server sin efectos laterales ni consultas a Redis.
 *
 * Un aviso antiguo enviado y aún capturado NO demuestra pérdida en Redis:
 * puede estar pendiente de consumo, reservado o fallando. Requiere análisis.
 */
final class MedirEstadoBuzonWhatsapp
{
    /**
     * @return array{pendientes_publicacion: int, pendientes_atrasados: int,
     *     publicados_sin_normalizar: int, publicados_antiguos: int,
     *     reservas_vencidas: int}
     */
    public function ejecutar(int $minutos, ?int $grupoId = null): array
    {
        $ahora = now('UTC');
        $umbral = $ahora->copy()->subMinutes($minutos)->format('Y-m-d H:i:s.u');
        $ahoraSql = $ahora->format('Y-m-d H:i:s.u');

        $base = MensajeBuzonSalida::query()
            ->join('mensajero_eventos_crudos_whatsapp as evento', 'evento.id', '=', 'mensajero_buzon_salida.entidad_id')
            ->join('mensajero_chats_monitoreados as grupo', 'grupo.id', '=', 'evento.chat_monitoreado_id')
            ->where('mensajero_buzon_salida.tema', 'whatsapp.crudo.aceptado')
            ->where('mensajero_buzon_salida.tipo_entidad', 'evento_crudo_whatsapp')
            ->where('evento.estado_procesamiento', 'capturado')
            ->where('grupo.tipo_chat', 'grupo')
            ->where('grupo.es_monitoreado', true)
            ->where('grupo.es_activo', true)
            ->whereColumn('grupo.conexion_id', 'evento.conexion_id');

        if ($grupoId !== null) {
            $base->where('grupo.id', $grupoId);
        }

        return [
            'pendientes_publicacion' => (clone $base)
                ->whereNull('mensajero_buzon_salida.enviado_en')
                ->count('mensajero_buzon_salida.id'),
            'pendientes_atrasados' => (clone $base)
                ->whereNull('mensajero_buzon_salida.enviado_en')
                ->where('mensajero_buzon_salida.creado_en', '<=', $umbral)
                ->count('mensajero_buzon_salida.id'),
            'publicados_sin_normalizar' => (clone $base)
                ->whereNotNull('mensajero_buzon_salida.enviado_en')
                ->count('mensajero_buzon_salida.id'),
            'publicados_antiguos' => (clone $base)
                ->whereNotNull('mensajero_buzon_salida.enviado_en')
                ->where('mensajero_buzon_salida.enviado_en', '<=', $umbral)
                ->count('mensajero_buzon_salida.id'),
            'reservas_vencidas' => (clone $base)
                ->whereNull('mensajero_buzon_salida.enviado_en')
                ->whereNotNull('mensajero_buzon_salida.reservado_hasta')
                ->where('mensajero_buzon_salida.reservado_hasta', '<=', $ahoraSql)
                ->count('mensajero_buzon_salida.id'),
        ];
    }
}
