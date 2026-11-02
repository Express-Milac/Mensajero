<?php

namespace Tests\Unidad;

use App\Services\IA\PresupuestoPublicacionesGemini;
use Tests\TestCase;

/**
 * No consulta Google ni modifica SQL Server: comprueba exclusivamente
 * la semántica de los límites configurados.
 */
final class PresupuestoGeminiSinLimiteTest extends TestCase
{
    public function test_un_cero_explicito_desactiva_la_cuota_del_piloto(): void
    {
        config([
            'mensajero.ia.conciliacion_maximo_dia_grupo' => 0,
            'mensajero.ia.lotes_habilitados' => false,
        ]);

        $presupuesto = new PresupuestoPublicacionesGemini();

        $this->assertSame(PHP_INT_MAX, $presupuesto->limiteDiario());
        $this->assertTrue($presupuesto->permiteReserva(5));
    }

    public function test_los_limites_normales_se_mantienen(): void
    {
        config([
            'mensajero.ia.conciliacion_maximo_dia_grupo' => 3,
            'mensajero.ia.lotes_habilitados' => false,
        ]);

        $this->assertSame(3, (new PresupuestoPublicacionesGemini())->limiteDiario());
    }

    public function test_un_valor_invalido_falla_cerrado(): void
    {
        config([
            'mensajero.ia.conciliacion_maximo_dia_grupo' => -1,
            'mensajero.ia.lotes_habilitados' => false,
        ]);

        $presupuesto = new PresupuestoPublicacionesGemini();

        $this->assertSame(0, $presupuesto->limiteDiario());
        $this->assertFalse($presupuesto->permiteReserva(5));
    }

    public function test_los_lotes_conservan_su_tope_independiente(): void
    {
        config([
            'mensajero.ia.conciliacion_maximo_dia_grupo' => 0,
            'mensajero.ia.lotes_habilitados' => true,
            'mensajero.ia.lotes_maximo_dia_grupo' => 6,
        ]);

        $this->assertSame(6, (new PresupuestoPublicacionesGemini())->limiteDiario());
    }
}
