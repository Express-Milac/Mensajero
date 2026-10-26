<?php

namespace App\Services\IA;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cupo compartido por grupo y día UTC, tanto para mensajes individuales
 * como para lotes. Debe consultarse bajo el mismo bloqueo SQL del grupo.
 *
 * Una reserva incierta también consume cupo: nunca suponer que Google
 * no facturó una solicitud fallida.
 */
final class PresupuestoPublicacionesGemini
{
    public function limiteDiario(): int
    {
        $piloto = (int) config('mensajero.ia.conciliacion_maximo_dia_grupo', 10);
        if ($piloto < 1 || $piloto > 30) {
            return 0;
        }

        if (config('mensajero.ia.lotes_habilitados') === true) {
            $lotes = (int) config('mensajero.ia.lotes_maximo_dia_grupo', 10);
            if ($lotes < 1 || $lotes > 30) {
                return 0;
            }
            return min($piloto, $lotes);
        }

        return $piloto;
    }

    public function consumidasHoy(int $grupoId): int
    {
        $inicioDia = now('UTC')->startOfDay()->format('Y-m-d H:i:s.u');
        $individuales = DB::table('mensajero_control_publicaciones_gemini')
            ->where('chat_monitoreado_id', $grupoId)
            ->where('reservado_en', '>=', $inicioDia)
            ->count();

        $lotes = 0;
        if (Schema::hasTable('mensajero_lotes_gemini')) {
            $lotes = DB::table('mensajero_lotes_gemini')
                ->where('chat_monitoreado_id', $grupoId)
                ->where('reservado_en', '>=', $inicioDia)
                ->count();
        }

        return $individuales + $lotes;
    }

    public function permiteReserva(int $grupoId): bool
    {
        $limite = $this->limiteDiario();
        return $limite > 0 && $this->consumidasHoy($grupoId) < $limite;
    }
}
