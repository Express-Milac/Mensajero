<?php

namespace Tests\Funcional;

use App\Jobs\AnalizarMensajeGeminiTrabajo;
use App\Services\IA\ClienteGemini;
use App\Services\IA\PoliticaEnvioGemini;
use App\Services\IA\PrepararContextoGemini;
use App\Services\IA\ValidarResultadoGemini;
use DateTimeImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

final class GeminiContratoTest extends TestCase
{
    public function test_el_trabajo_gemini_puede_despacharse_sin_usar_redis_real(): void
    {
        Bus::fake();
        config()->set('mensajero.ia.cola', 'mensajero_ia');

        AnalizarMensajeGeminiTrabajo::dispatch(73);

        Bus::assertDispatched(AnalizarMensajeGeminiTrabajo::class, function (AnalizarMensajeGeminiTrabajo $trabajo): bool {
            return $trabajo->mensajeId === 73
                && $trabajo->connection === 'redis'
                && $trabajo->queue === 'mensajero_ia';
        });
    }

    public function test_el_piloto_rechaza_publicar_sin_mensaje_explicito(): void
    {
        $this->artisan('mensajero:analizar-gemini', [
            '--grupo' => '10020',
            '--limite' => '1',
            '--aplicar' => true,
        ])->assertExitCode(1);
    }

    public function test_el_piloto_rechaza_identificador_invalido(): void
    {
        $this->artisan('mensajero:analizar-gemini', [
            '--grupo' => '10020',
            '--mensaje-id' => '-1',
            '--aplicar' => true,
        ])->assertExitCode(1);
    }

    public function test_gemini_permanece_cerrado_sin_todas_las_autorizaciones(): void
    {
        config()->set('mensajero.ia', [
            'habilitada' => true,
            'envio_autorizado' => false,
            'servicio_pagado_confirmado' => true,
            'grupos_autorizados' => [10020],
            'fecha_inicio' => '2026-10-21T12:00:00-06:00',
            'clave_api' => 'clave-ficticia-no-funcional',
        ]);

        $politica = new PoliticaEnvioGemini;
        $this->assertFalse($politica->configuracionCompleta());
        $this->assertFalse($politica->autoriza(10020, new DateTimeImmutable('2026-10-21T13:00:00-06:00')));

        config()->set('mensajero.ia.envio_autorizado', true);
        $this->assertTrue($politica->autoriza(10020, new DateTimeImmutable('2026-10-21T13:00:00-06:00')));
        $this->assertFalse($politica->autoriza(10021, new DateTimeImmutable('2026-10-21T13:00:00-06:00')));
        $this->assertFalse($politica->autoriza(10020, new DateTimeImmutable('2026-10-20T13:00:00-06:00')));
    }

    public function test_el_contexto_oculta_telefonos_y_correos(): void
    {
        $limpio = (new PrepararContextoGemini)->limpiar('Llama al +52 461 123 4567 o a prueba@milac.example por la descarga.');
        $this->assertStringNotContainsString('461', $limpio);
        $this->assertStringNotContainsString('prueba@', $limpio);
        $this->assertStringContainsString('descarga', $limpio);
    }

    public function test_rechaza_referencias_a_mensajes_ajenos_o_anteriores_sin_el_objetivo(): void
    {
        $validador = new ValidarResultadoGemini;
        $datos = [
            'es_evento' => true,
            'categoria' => 'incidencia',
            'titulo' => 'Retraso en la descarga',
            'resumen' => 'Se reportó una demora y aún no hay hora confirmada.',
            'mensaje_ids' => [101, 999],
        ];

        $this->expectException(RuntimeException::class);
        $validador->validar($datos, [101, 102], 102);
    }

    public function test_gemini_produce_json_y_no_envia_identificadores_de_whatsapp(): void
    {
        config()->set('mensajero.ia.modelo', 'gemini-3.5-flash-lite');
        config()->set('mensajero.ia.clave_api', 'clave-ficticia-no-funcional');

        $modelo = [
            'es_evento' => true,
            'categoria' => 'incidencia',
            'titulo' => 'Retraso en la descarga',
            'resumen' => 'El operador reporta espera para iniciar la descarga.',
            'mensaje_ids' => [101, 102],
        ];

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'finishReason' => 'STOP',
                    'content' => ['parts' => [['text' => json_encode($modelo)]]],
                ]],
                'usageMetadata' => ['promptTokenCount' => 80, 'candidatesTokenCount' => 40],
            ], 200),
        ]);

        $cliente = new ClienteGemini;
        $respuesta = $cliente->analizar([
            ['id' => 101, 'texto' => 'Llegué al cliente.', 'tipo' => 'texto'],
            ['id' => 102, 'texto' => 'Aún no me descargan.', 'tipo' => 'texto'],
        ]);

        $validado = (new ValidarResultadoGemini)->validar($respuesta['resultado'], [101, 102], 102);
        $this->assertTrue($validado['es_evento']);
        $this->assertSame([101, 102], $validado['mensaje_ids']);
        $this->assertSame(80, $respuesta['tokens_entrada']);

        Http::assertSent(function (Request $solicitud): bool {
            return str_contains($solicitud->url(), 'gemini-3.5-flash-lite:generateContent')
                && ! str_contains($solicitud->url(), 'clave-ficticia')
                && $solicitud->hasHeader('x-goog-api-key', 'clave-ficticia-no-funcional')
                && $solicitud['generationConfig']['responseMimeType'] === 'application/json';
        });
    }

    public function test_codigo_401_informa_razon_google_sin_exponer_credenciales(): void
    {
        config()->set('mensajero.ia.modelo', 'gemini-3.5-flash-lite');
        config()->set('mensajero.ia.clave_api', 'clave-ficticia-no-funcional');

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'error' => [
                    'status' => 'UNAUTHENTICATED',
                    'message' => 'No registrar esta clave: clave-ficticia-no-funcional',
                    'details' => [['reason' => 'API_KEY_SERVICE_BLOCKED']],
                ],
            ], 401),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HTTP 401; estado UNAUTHENTICATED; razón API_KEY_SERVICE_BLOCKED');

        (new ClienteGemini)->analizar([['id' => 74, 'texto' => 'Solo un mensaje ficticio.', 'tipo' => 'texto']]);
    }

    public function test_una_respuesta_incompleta_no_se_acepta(): void
    {
        config()->set('mensajero.ia.modelo', 'gemini-3.5-flash-lite');
        config()->set('mensajero.ia.clave_api', 'clave-ficticia-no-funcional');
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['finishReason' => 'MAX_TOKENS', 'content' => ['parts' => [['text' => '{}']]]]],
            ], 200),
        ]);

        $this->expectException(RuntimeException::class);
        (new ClienteGemini)->analizar([['id' => 1, 'texto' => 'Ejemplo.', 'tipo' => 'texto']]);
    }
}
