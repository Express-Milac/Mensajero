<?php

namespace App\Services\IA;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Puente experimental y NO operativo entre cotizador y libro SQL.
 *
 * No está conectado a PublicarLoteGemini ni a los clientes HTTP. Una reserva
 * aquí NO representa permiso de envío pagado ni una tarifa actual confirmada.
 * Falla cerrado ante cualquier configuración desconocida o insuficiente.
 */
final class VincularCotizacionPresupuestoGemini
{
    public function __construct(
        private readonly CotizarCostoReferenciaGemini $cotizador,
        private readonly ReservarPresupuestoGemini $libro,
    ) {}

    /**
     * @return string deshabilitado | cotizacion_insuficiente |
     *                datos_invalidos | fuente_no_valida | ya_reservado |
     *                limite_diario | limite_mensual | reservada
     */
    public function reservar(string $tipoFuente, int $fuenteId, int $grupoId): string
    {
        if (! in_array($tipoFuente, ['mensaje', 'lote'], true)
            || $fuenteId < 1 || $grupoId < 1) {
            return 'datos_invalidos';
        }

        $modelo = config('mensajero.ia.modelo');
        if (! is_string($modelo)) {
            return 'deshabilitado';
        }

        $referencia = $this->cotizador->estimar($modelo, $tipoFuente);
        if ($referencia === null
            || config('mensajero.ia.presupuesto_monetario.habilitado') !== true
            || config('mensajero.ia.presupuesto_monetario.tarifa_validada') !== true
            || config('mensajero.ia.presupuesto_monetario.tarifa_referencia')
                !== CotizarCostoReferenciaGemini::REFERENCIA_TARIFA) {
            return 'deshabilitado';
        }

        $importe = config('mensajero.ia.presupuesto_monetario.reserva_maxima_microusd');
        if (! is_int($importe) || $importe < $referencia['importe_referencia_microusd']) {
            return 'cotizacion_insuficiente';
        }

        if (! Schema::hasTable('mensajero_chats_monitoreados')
            || ! Schema::hasTable('mensajero_reservas_consumo_gemini')) {
            return 'deshabilitado';
        }

        // Estos vínculos son de contabilidad, NO equivalen a verificar
        // consentimiento específico: eso pertenece al circuito de despacho.
        $grupoValido = DB::table('mensajero_chats_monitoreados')
            ->where('id', $grupoId)->where('tipo_chat', 'grupo')
            ->where('es_activo', true)->where('es_monitoreado', true)
            ->where('es_visible_tablero', true)->exists();

        if (! $grupoValido) {
            return 'fuente_no_valida';
        }

        if ($tipoFuente === 'lote') {
            if (! Schema::hasTable('mensajero_lotes_gemini')) {
                return 'deshabilitado';
            }
            $fuenteValida = DB::table('mensajero_lotes_gemini')
                ->where('id', $fuenteId)->where('chat_monitoreado_id', $grupoId)
                ->whereIn('estado', ['reservado', 'publicado'])->exists();
        } else {
            if (! Schema::hasTable('mensajero_mensajes_whatsapp')) {
                return 'deshabilitado';
            }
            $fuenteValida = DB::table('mensajero_mensajes_whatsapp')
                ->where('id', $fuenteId)->where('chat_monitoreado_id', $grupoId)
                ->where('estado_captura', 'normalizado')->exists();
        }

        if (! $fuenteValida) {
            return 'fuente_no_valida';
        }

        return $this->libro->reservar($tipoFuente, $fuenteId, $grupoId);
    }
}
