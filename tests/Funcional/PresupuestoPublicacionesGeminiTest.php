<?php

namespace Tests\Funcional;

use App\Services\IA\PresupuestoPublicacionesGemini;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class PresupuestoPublicacionesGeminiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('PDO SQLite necesario para validar presupuesto con tablas aisladas.');
        }

        Schema::create('mensajero_control_publicaciones_gemini', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->unsignedBigInteger('chat_monitoreado_id');
            $tabla->dateTime('reservado_en');
        });

        config()->set('mensajero.ia.conciliacion_maximo_dia_grupo', 10);
        config()->set('mensajero.ia.lotes_habilitados', false);
    }

    private function reservarIndividuales(int $cuantos): void
    {
        $fecha = now('UTC')->format('Y-m-d H:i:s.u');
        for ($i = 0; $i < $cuantos; $i++) {
            DB::table('mensajero_control_publicaciones_gemini')->insert([
                'chat_monitoreado_id' => 10196, 'reservado_en' => $fecha,
            ]);
        }
    }

    public function test_mantiene_cupo_original_cuando_no_existe_tabla_lotes(): void
    {
        $this->reservarIndividuales(9);
        $this->assertTrue(app(PresupuestoPublicacionesGemini::class)->permiteReserva(10196));
        $this->reservarIndividuales(1);
        $this->assertFalse(app(PresupuestoPublicacionesGemini::class)->permiteReserva(10196));
    }

    public function test_cuenta_reservas_de_ambos_circuitos_incluso_si_lotes_estan_apagados(): void
    {
        Schema::create('mensajero_lotes_gemini', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->unsignedBigInteger('chat_monitoreado_id');
            $tabla->dateTime('reservado_en');
        });

        $this->reservarIndividuales(9);
        $this->assertTrue(app(PresupuestoPublicacionesGemini::class)->permiteReserva(10196));
        DB::table('mensajero_lotes_gemini')->insert([
            'chat_monitoreado_id' => 10196,
            'reservado_en' => now('UTC')->format('Y-m-d H:i:s.u'),
        ]);
        $this->assertSame(10, app(PresupuestoPublicacionesGemini::class)->consumidasHoy(10196));
        $this->assertFalse(app(PresupuestoPublicacionesGemini::class)->permiteReserva(10196));
        $this->assertTrue(app(PresupuestoPublicacionesGemini::class)->permiteReserva(10197));
    }

    public function test_en_modo_lotes_se_utiliza_limite_mas_restrictivo(): void
    {
        config()->set('mensajero.ia.lotes_habilitados', true);
        config()->set('mensajero.ia.lotes_maximo_dia_grupo', 3);
        $this->reservarIndividuales(3);
        $this->assertSame(3, app(PresupuestoPublicacionesGemini::class)->limiteDiario());
        $this->assertFalse(app(PresupuestoPublicacionesGemini::class)->permiteReserva(10196));
    }

    public function test_limite_invalido_bloquea_en_lugar_de_dejar_cupo_ilimitado(): void
    {
        config()->set('mensajero.ia.conciliacion_maximo_dia_grupo', 31);
        $this->assertFalse(app(PresupuestoPublicacionesGemini::class)->permiteReserva(10196));
    }
}
