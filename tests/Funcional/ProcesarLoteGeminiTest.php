<?php

namespace Tests\Funcional;

use App\Jobs\AnalizarLoteGeminiTrabajo;
use App\Services\IA\ProcesarLoteGemini;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ProcesarLoteGeminiTest extends TestCase
{
    public function test_trabajo_tiene_un_intento_y_cola_distinta_del_piloto(): void
    {
        $trabajo = new AnalizarLoteGeminiTrabajo(27);

        $this->assertSame(1, $trabajo->tries);
        $this->assertSame(45, $trabajo->timeout);
        $this->assertSame('redis', $trabajo->connection);
        $this->assertSame('mensajero_ia_lotes', $trabajo->queue);
        $this->assertSame(27, $trabajo->loteId);
    }

    public function test_lotes_apagados_no_consultan_sql_ni_google(): void
    {
        config()->set('mensajero.ia.lotes_habilitados', false);
        $this->simularHttpSinRed();

        $this->assertSame('deshabilitado', app(ProcesarLoteGemini::class)->ejecutar(27));
        Http::assertNothingSent();
    }

    public function test_identificador_invalido_no_intenta_google(): void
    {
        $this->simularHttpSinRed();

        $this->assertSame('deshabilitado', app(ProcesarLoteGemini::class)->ejecutar(0));
        Http::assertNothingSent();
    }
}
