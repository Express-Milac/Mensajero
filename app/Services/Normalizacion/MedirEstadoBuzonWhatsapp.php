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

    /**
     * Desglosa solamente avisos aún no publicados. No incluye remitentes,
     * contenidos, nombres de grupos ni identificadores externos.
     *
     * @return array{grupos: array<int, array{grupo_id:int, conexion_id:int,
     *     pendientes:int, atrasados:int, mayores_24h:int, mayores_7d:int,
     *     prefijo_ensayo:bool}>, truncado:bool}
     */
    public function desglosarPendientes(int $minutos, ?int $grupoId = null): array
    {
        $ahora = now('UTC');
        $umbral = $ahora->copy()->subMinutes($minutos)->format('Y-m-d H:i:s.u');
        $hace24Horas = $ahora->copy()->subDay()->format('Y-m-d H:i:s.u');
        $hace7Dias = $ahora->copy()->subDays(7)->format('Y-m-d H:i:s.u');

        $consulta = MensajeBuzonSalida::query()
            ->join('mensajero_eventos_crudos_whatsapp as evento', 'evento.id', '=', 'mensajero_buzon_salida.entidad_id')
            ->join('mensajero_chats_monitoreados as grupo', 'grupo.id', '=', 'evento.chat_monitoreado_id')
            ->join('mensajero_conexiones_whatsapp as conexion', 'conexion.id', '=', 'evento.conexion_id')
            ->where('mensajero_buzon_salida.tema', 'whatsapp.crudo.aceptado')
            ->where('mensajero_buzon_salida.tipo_entidad', 'evento_crudo_whatsapp')
            ->whereNull('mensajero_buzon_salida.enviado_en')
            ->where('evento.estado_procesamiento', 'capturado')
            ->where('grupo.tipo_chat', 'grupo')
            ->where('grupo.es_monitoreado', true)
            ->where('grupo.es_activo', true)
            ->whereColumn('grupo.conexion_id', 'evento.conexion_id');

        if ($grupoId !== null) {
            $consulta->where('grupo.id', $grupoId);
        }

        $registros = $consulta
            ->selectRaw(
                "evento.chat_monitoreado_id AS grupo_id,
                evento.conexion_id AS conexion_id,
                COUNT_BIG(*) AS pendientes,
                SUM(CASE WHEN mensajero_buzon_salida.creado_en <= ? THEN 1 ELSE 0 END) AS atrasados,
                SUM(CASE WHEN mensajero_buzon_salida.creado_en <= ? THEN 1 ELSE 0 END) AS mayores_24h,
                SUM(CASE WHEN mensajero_buzon_salida.creado_en <= ? THEN 1 ELSE 0 END) AS mayores_7d,
                MAX(CASE WHEN conexion.clave_conexion LIKE 'ensayo-redis-%' THEN 1 ELSE 0 END) AS prefijo_ensayo",
                [$umbral, $hace24Horas, $hace7Dias],
            )
            ->groupBy('evento.chat_monitoreado_id', 'evento.conexion_id')
            ->orderByDesc('pendientes')
            ->orderBy('evento.chat_monitoreado_id')
            ->limit(51)
            ->get();

        $truncado = $registros->count() > 50;

        return [
            'grupos' => $registros->take(50)->map(static fn ($registro): array => [
                'grupo_id' => (int) $registro->grupo_id,
                'conexion_id' => (int) $registro->conexion_id,
                'pendientes' => (int) $registro->pendientes,
                'atrasados' => (int) $registro->atrasados,
                'mayores_24h' => (int) $registro->mayores_24h,
                'mayores_7d' => (int) $registro->mayores_7d,
                'prefijo_ensayo' => (int) $registro->prefijo_ensayo === 1,
            ])->values()->all(),
            'truncado' => $truncado,
        ];
    }
}
