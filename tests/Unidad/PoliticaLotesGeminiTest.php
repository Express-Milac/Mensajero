<?php

namespace Tests\Unidad;

use App\Services\IA\PoliticaLotesGemini;
use DateTimeImmutable;
use Tests\TestCase;

/**
 * Inicializa solamente el contenedor de Laravel, sin consultar SQL ni Redis.
 */
final class PoliticaLotesGeminiTest extends TestCase
{
    protected function tearDown(): void
    {
        if (function_exists('app') && app()->bound('config')) {
            config()->set('mensajero.ia.lotes_habilitados', false);
            config()->set('mensajero.ia.lotes_fecha_inicio', '');
            config()->set('mensajero.ia.lotes_maximo_dia_grupo', 10);
        }
        parent::tearDown();
    }

    public function test_habilitacion_exige_corte_y_presupuesto(): void
    {
        config()->set('mensajero.ia.lotes_habilitados', true);
        config()->set('mensajero.ia.lotes_fecha_inicio', '');
        config()->set('mensajero.ia.lotes_maximo_dia_grupo', 10);
        $this->assertFalse((new PoliticaLotesGemini)->habilitada());

        config()->set('mensajero.ia.lotes_fecha_inicio', '2026-10-26T12:00:00-06:00');
        $this->assertTrue((new PoliticaLotesGemini)->habilitada());

        config()->set('mensajero.ia.lotes_maximo_dia_grupo', 31);
        $this->assertFalse((new PoliticaLotesGemini)->habilitada());
    }

    public function test_lotes_no_retroceden_su_fecha_de_inicio(): void
    {
        config()->set('mensajero.ia.lotes_habilitados', true);
        config()->set('mensajero.ia.lotes_maximo_dia_grupo', 10);
        config()->set('mensajero.ia.lotes_fecha_inicio', '2026-10-26T12:00:00-06:00');
        $politica = new PoliticaLotesGemini;

        $this->assertFalse($politica->posteriorAlCorte(new DateTimeImmutable('2026-10-26T11:59:59-06:00')));
        $this->assertTrue($politica->posteriorAlCorte(new DateTimeImmutable('2026-10-26T12:00:00-06:00')));
    }
}
