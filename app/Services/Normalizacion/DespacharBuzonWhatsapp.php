<?php

namespace App\Services\Normalizacion;

use App\Jobs\NormalizarEventoWhatsappTrabajo;
use App\Models\MensajeBuzonSalida;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Throwable;

/**
 * Buzón SQL Server -> Redis con reserva recuperable y entrega al menos una vez.
 * Marcar "enviado" significa publicado a Redis, no normalizado en SQL.
 */
final class DespacharBuzonWhatsapp
{
    /**
     * @return array{publicados: int, fallidos: int}
     */
    public function ejecutar(int $limite = 100, ?int $eventoCrudoId = null): array
    {
        $resultado = ['publicados' => 0, 'fallidos' => 0];
        $limite = max(1, min($limite, 500));

        for ($indice = 0; $indice < $limite; $indice++) {
            $tarea = DB::transaction(function () use ($eventoCrudoId): ?MensajeBuzonSalida {
                $ahora = now('UTC');
                $consulta = MensajeBuzonSalida::query()
                    ->where('tema', 'whatsapp.crudo.aceptado')
                    ->where('tipo_entidad', 'evento_crudo_whatsapp')
                    ->whereNotNull('entidad_id')
                    ->whereNull('enviado_en')
                    ->where('disponible_en', '<=', $ahora)
                    ->where(function ($q) use ($ahora) {
                        $q->whereNull('reservado_hasta')->orWhere('reservado_hasta', '<=', $ahora);
                    });

                if ($eventoCrudoId !== null) {
                    $consulta->where('entidad_id', $eventoCrudoId);
                }

                $tarea = $consulta->orderBy('id')->lockForUpdate()->first();

                if ($tarea === null) {
                    return null;
                }

                $tarea->reservado_hasta = $ahora->addMinutes(2);
                $tarea->intentos = min(65535, (int) $tarea->intentos + 1);
                $tarea->save();

                return $tarea;
            }, 3);

            if ($tarea === null) {
                break;
            }

            try {
                Queue::connection('redis')->pushOn(
                    'mensajero_normalizacion',
                    new NormalizarEventoWhatsappTrabajo((int) $tarea->entidad_id),
                );

                MensajeBuzonSalida::query()
                    ->whereKey($tarea->id)
                    ->whereNull('enviado_en')
                    ->update([
                        'enviado_en' => now('UTC'),
                        'reservado_hasta' => null,
                        'ultimo_error' => null,
                    ]);

                $resultado['publicados']++;
            } catch (Throwable $error) {
                $segundos = min(3600, 2 ** min((int) $tarea->intentos, 11));

                MensajeBuzonSalida::query()
                    ->whereKey($tarea->id)
                    ->whereNull('enviado_en')
                    ->update([
                        'reservado_hasta' => null,
                        'disponible_en' => now('UTC')->addSeconds($segundos),
                        // Evita registrar credenciales o respuestas de proveedores.
                        'ultimo_error' => 'Error al publicar en Redis: '.class_basename($error),
                    ]);

                $resultado['fallidos']++;
            }
        }

        return $resultado;
    }
}
