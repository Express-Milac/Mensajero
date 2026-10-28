<?php

namespace Tests\Unidad;

use App\Services\IA\CotizarCostoReferenciaGemini;
use PHPUnit\Framework\TestCase;

/**
 * Pruebas puras de aritmética y catálogo. No DB, Redis, Http ni API Gemini.
 */
final class CotizarCostoReferenciaGeminiTest extends TestCase
{
    public function test_circuito_individual_reserva_referencia_del_maximo_fisico_de_entrada(): void
    {
        $cotizacion = (new CotizarCostoReferenciaGemini())
            ->estimar('gemini-3.5-flash-lite', 'mensaje');

        $this->assertIsArray($cotizacion);
        $this->assertSame(1_048_576, $cotizacion['maximo_entrada_tokens']);
        $this->assertSame(900, $cotizacion['maximo_salida_tokens']);
        // 1,048,576 * USD 0.30 / 1M = 0.3145728 USD -> techo 314,573 microUSD.
        $this->assertSame(314_573, $cotizacion['reserva_entrada_microusd']);
        // 900 * USD 2.50 / 1M = 2,250 microUSD.
        $this->assertSame(2_250, $cotizacion['reserva_salida_microusd']);
        $this->assertSame(316_823, $cotizacion['importe_referencia_microusd']);
        $this->assertTrue($cotizacion['solo_referencia']);
    }

    public function test_circuito_lote_incluye_todos_los_tokens_de_salida_y_pensamiento(): void
    {
        $cotizacion = (new CotizarCostoReferenciaGemini())
            ->estimar('gemini-3.5-flash-lite', 'lote');

        $this->assertIsArray($cotizacion);
        $this->assertSame(1800, $cotizacion['maximo_salida_tokens']);
        $this->assertSame(4_500, $cotizacion['reserva_salida_microusd']);
        $this->assertSame(314_573, $cotizacion['reserva_entrada_microusd']);
        $this->assertSame(319_073, $cotizacion['importe_referencia_microusd']);
    }

    public function test_modelos_desconocidos_y_otras_modalidades_fallan_cerrado(): void
    {
        $cotizador = new CotizarCostoReferenciaGemini();

        $this->assertNull($cotizador->estimar('gemini-3.5-flash', 'mensaje'));
        $this->assertNull($cotizador->estimar('gemini-2.5-flash-lite', 'lote'));
        $this->assertNull($cotizador->estimar('gemini-3.5-flash-lite', 'audio'));
        $this->assertNull($cotizador->estimar('gemini-3.5-flash-lite', ''));
    }

    public function test_cotizacion_identifica_tarifa_fija_y_no_aprueba_el_pago(): void
    {
        $cotizacion = (new CotizarCostoReferenciaGemini())
            ->estimar(CotizarCostoReferenciaGemini::MODELO, 'mensaje');

        $this->assertSame('2026-10-28', CotizarCostoReferenciaGemini::FECHA_TARIFA);
        $this->assertSame('2026-10-28', $cotizacion['tarifa_fecha']);
        $this->assertSame('gemini-3.5-flash-lite', $cotizacion['modelo']);
        $this->assertSame('mensaje', $cotizacion['circuito']);
        $this->assertTrue($cotizacion['solo_referencia']);
    }
}
