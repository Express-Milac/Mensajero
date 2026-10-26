<?php

namespace Tests\Funcional;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class SimularLotesGeminiTest extends TestCase
{
    public function test_rechaza_grupos_invalidos_antes_de_consultar_sql_o_google(): void
    {
        Http::fake();
        $this->artisan('mensajero:simular-lotes-gemini', [
            '--grupo' => '-1',
        ])->assertExitCode(1);
        $this->artisan('mensajero:simular-lotes-gemini', [
            '--grupo' => 'no-es-un-grupo',
        ])->assertExitCode(1);
        Http::assertNothingSent();
    }

    public function test_rechaza_limites_fuera_de_rango_sin_publicar(): void
    {
        Http::fake();
        $this->artisan('mensajero:simular-lotes-gemini', [
            '--grupo' => '10196',
            '--limite' => '101',
        ])->assertExitCode(1);
        Http::assertNothingSent();
    }
}
