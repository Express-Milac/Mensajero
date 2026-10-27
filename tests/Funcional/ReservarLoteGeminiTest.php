<?php

namespace Tests\Funcional;

use App\Services\IA\ReservarLoteGemini;
use DomainException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ReservarLoteGeminiTest extends TestCase
{
    public function test_rechaza_lotes_sin_habilitacion_sin_tocar_sql_ni_google(): void
    {
        config()->set('mensajero.ia.lotes_habilitados', false);
        $this->simularHttpSinRed();

        try {
            app(ReservarLoteGemini::class)->reservar(10196, [101, 102]);
            $this->fail('No debería reservar sin consentimiento contextual.');
        } catch (DomainException $error) {
            $this->assertStringContainsString('no autorizado', $error->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_rechaza_ids_duplicados_antes_de_usar_sql(): void
    {
        $this->simularHttpSinRed();

        try {
            app(ReservarLoteGemini::class)->reservar(10196, [101, 101]);
            $this->fail('No debería aceptar IDs duplicados.');
        } catch (DomainException $error) {
            $this->assertStringContainsString('únicos', $error->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_rechaza_lote_con_mas_de_seis_mensajes(): void
    {
        $this->expectException(DomainException::class);
        app(ReservarLoteGemini::class)->reservar(10196, [1, 2, 3, 4, 5, 6, 7]);
    }
}
