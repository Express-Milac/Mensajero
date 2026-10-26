<?php

namespace Tests\Funcional;

use App\Jobs\AnalizarMensajeGeminiTrabajo;
use App\Services\IA\ConciliarPublicacionesGemini;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ConciliacionGeminiTest extends TestCase
{
    public function test_la_conciliacion_esta_deshabilitada_por_defecto_y_no_hace_llamadas_a_google(): void
    {
        Http::fake();
        config()->set('mensajero.ia.conciliacion_automatica', false);

        $this->assertFalse(app(ConciliarPublicacionesGemini::class)->habilitada());
        Http::assertNothingSent();
    }

    public function test_la_conciliacion_exige_nuevo_corte_y_limite_valido(): void
    {
        config()->set('mensajero.ia', [
            'habilitada' => true,
            'envio_autorizado' => true,
            'servicio_pagado_confirmado' => true,
            'ejecucion_automatica' => true,
            'clave_api' => 'clave-ficticia-no-funcional',
            'grupos_autorizados' => [10020],
            'fecha_inicio' => '2026-10-22T14:19:08-06:00',
            'conciliacion_automatica' => true,
            'conciliacion_fecha_inicio' => '',
            'conciliacion_maximo_dia_grupo' => 10,
        ]);

        $this->assertFalse(app(ConciliarPublicacionesGemini::class)->habilitada());
        config()->set('mensajero.ia.conciliacion_fecha_inicio', '2026-10-24T09:00:00-06:00');
        $this->assertTrue(app(ConciliarPublicacionesGemini::class)->habilitada());

        config()->set('mensajero.ia.conciliacion_maximo_dia_grupo', 0);
        $this->assertFalse(app(ConciliarPublicacionesGemini::class)->habilitada());
        config()->set('mensajero.ia.conciliacion_maximo_dia_grupo', 31);
        $this->assertFalse(app(ConciliarPublicacionesGemini::class)->habilitada());
    }

    public function test_el_comando_rechaza_limites_incorrectos_sin_consultar_google(): void
    {
        Http::fake();

        $this->artisan('mensajero:conciliar-gemini', ['--limite' => '11'])
            ->assertExitCode(1);
        $this->artisan('mensajero:conciliar-gemini', ['--grupo' => '-2'])
            ->assertExitCode(1);

        Http::assertNothingSent();
    }

    public function test_el_trabajo_no_reintenta_una_llamada_remota_de_resultado_incierto(): void
    {
        $trabajo = new AnalizarMensajeGeminiTrabajo(79);

        $this->assertSame(1, $trabajo->tries);
        $this->assertSame('redis', $trabajo->connection);
    }
    public function test_proyeccion_rechaza_limite_invalido_sin_consultar_google(): void
    {
        Http::fake();

        $this->artisan('mensajero:proyectar-operacion', ['--todos' => true, '--limite' => '101'])
            ->assertExitCode(1);

        Http::assertNothingSent();
    }
}
