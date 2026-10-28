<?php

namespace App\Services\IA;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Libro de reservas máximas configuradas (microUSD), sin envío de solicitudes.
 *
 * FASE 1: NO está conectado al publicador ni al cliente HTTP.
 * La reserva nominal NO prueba que su importe sea el costo máximo facturable.
 * Hasta verificar precios, modelo y tokens máximos, no habilitar Gemini real.
 */
final class ReservarPresupuestoGemini
{
    private const MAXIMO_MICROUSD = 1_000_000_000_000; // límite de validación de enteros

    /**
     * @return string deshabilitado | datos_invalidos | ya_reservado | limite_diario | limite_mensual | reservada
     */
    public function reservar(string $tipoFuente, int $fuenteId, int $grupoId): string
    {
        $importe = $this->importeConfigurado();
        if ($importe === null
            || ! Schema::hasTable('mensajero_candado_presupuesto_gemini')
            || ! Schema::hasTable('mensajero_reservas_consumo_gemini')) {
            return 'deshabilitado';
        }

        if (! in_array($tipoFuente, ['mensaje', 'lote'], true)
            || $fuenteId < 1 || $grupoId < 1) {
            return 'datos_invalidos';
        }

        $modelo = (string) config('mensajero.ia.modelo', '');
        $tarifa = (string) config('mensajero.ia.presupuesto_monetario.tarifa_referencia', '');
        if (! preg_match('/\Agemini-[A-Za-z0-9.-]+\z/D', $modelo)
            || ! preg_match('/\A[A-Za-z0-9._-]{8,90}\z/D', $tarifa)) {
            return 'deshabilitado';
        }

        $diario = config('mensajero.ia.presupuesto_monetario.limite_diario_microusd');
        $mensual = config('mensajero.ia.presupuesto_monetario.limite_mensual_microusd');
        if (! is_int($diario) || ! is_int($mensual)
            || $diario < $importe || $mensual < $importe
            || $diario > self::MAXIMO_MICROUSD || $mensual > self::MAXIMO_MICROUSD) {
            return 'deshabilitado';
        }

        return DB::transaction(function () use (
            $tipoFuente, $fuenteId, $grupoId, $importe, $modelo, $tarifa, $diario, $mensual,
        ): string {
            // Candado GLOBAL: dos grupos distintos compiten por la misma fila.
            // SQL Server mantiene el bloqueo hasta confirmar la transacción.
            $candado = DB::table('mensajero_candado_presupuesto_gemini')
                ->where('id', 1)->lockForUpdate()->first(['id']);
            if ($candado === null) {
                return 'deshabilitado';
            }

            if (DB::table('mensajero_reservas_consumo_gemini')
                ->where('tipo_fuente', $tipoFuente)
                ->where('fuente_id', $fuenteId)->exists()) {
                return 'ya_reservado';
            }

            if (! DB::table('mensajero_chats_monitoreados')
                ->where('id', $grupoId)->where('tipo_chat', 'grupo')->exists()) {
                return 'datos_invalidos';
            }

            $hoy = now('UTC');
            $inicioDia = $hoy->copy()->startOfDay()->format('Y-m-d H:i:s.u');
            $inicioMes = $hoy->copy()->startOfMonth()->format('Y-m-d H:i:s.u');

            // Todos los estados se incluyen, incluso "finalizada":
            // NO liberar cupo al finalizar ni tras resultados ambiguos.
            $base = DB::table('mensajero_reservas_consumo_gemini');
            $usadoDia = (int) (clone $base)->where('reservado_en', '>=', $inicioDia)
                ->sum('importe_reservado_microusd');
            $usadoMes = (int) (clone $base)->where('reservado_en', '>=', $inicioMes)
                ->sum('importe_reservado_microusd');

            // Restar evita overflow de sumar enteros de SQL/PHP.
            if ($usadoDia > $diario - $importe) {
                return 'limite_diario';
            }
            if ($usadoMes > $mensual - $importe) {
                return 'limite_mensual';
            }

            $ahora = $hoy->format('Y-m-d H:i:s.u');
            DB::table('mensajero_reservas_consumo_gemini')->insert([
                'chat_monitoreado_id' => $grupoId,
                'tipo_fuente' => $tipoFuente,
                'fuente_id' => $fuenteId,
                'modelo' => $modelo,
                'tarifa_referencia' => $tarifa,
                'importe_reservado_microusd' => $importe,
                'estado' => 'reservada',
                'reservado_en' => $ahora,
                'actualizado_en' => $ahora,
            ]);

            return 'reservada';
        }, 3);
    }

    /**
     * Una reserva incierta nunca se elimina ni devuelve cupo.
     * No existe aquí una operación de liberación automática.
     */
    public function marcarIncierta(string $tipoFuente, int $fuenteId): bool
    {
        if (! in_array($tipoFuente, ['mensaje', 'lote'], true) || $fuenteId < 1
            || ! Schema::hasTable('mensajero_reservas_consumo_gemini')) {
            return false;
        }

        try {
            return DB::table('mensajero_reservas_consumo_gemini')
                ->where('tipo_fuente', $tipoFuente)->where('fuente_id', $fuenteId)
                ->where('estado', 'reservada')
                ->update([
                    'estado' => 'incierta',
                    'actualizado_en' => now('UTC')->format('Y-m-d H:i:s.u'),
                ]) === 1;
        } catch (Throwable) {
            return false; // no alegar éxito ni emitir nuevos envíos por fallo SQL
        }
    }

    private function importeConfigurado(): ?int
    {
        if (config('mensajero.ia.presupuesto_monetario.habilitado') !== true
            || config('mensajero.ia.presupuesto_monetario.tarifa_validada') !== true) {
            return null;
        }

        $importe = config('mensajero.ia.presupuesto_monetario.reserva_maxima_microusd');
        return is_int($importe) && $importe > 0 && $importe <= self::MAXIMO_MICROUSD
            ? $importe : null;
    }
}
