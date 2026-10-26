<?php

namespace Tests\Funcional;

use App\Services\IA\ClienteGeminiLote;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

final class ClienteGeminiLoteTest extends TestCase
{
    private function contexto(): array
    {
        return [
            [
                'id' => 101, 'texto' => 'Llegó la unidad al patio.',
                'tipo' => 'texto', 'emisor' => 'participante_1', 'responde_a' => null,
            ],
            [
                'id' => 102, 'texto' => 'La carga no está lista; correo prueba@ejemplo.com.',
                'tipo' => 'texto', 'emisor' => 'participante_2', 'responde_a' => 101,
            ],
        ];
    }

    public function test_valida_dos_acontecimientos_en_una_sola_peticion_simulada(): void
    {
        config()->set('mensajero.ia.modelo', 'gemini-3.5-flash-lite');
        config()->set('mensajero.ia.clave_api', 'clave-ficticia-para-prueba');

        Http::preventStrayRequests();
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [[
                'finishReason' => 'STOP',
                'content' => ['parts' => [[
                    'text' => json_encode(['acontecimientos' => [
                        [
                            'categoria' => 'logistica',
                            'titulo' => 'Llegada al patio',
                            'resumen' => 'Un participante reportó que la unidad llegó al patio.',
                            'mensaje_ids' => [101],
                        ],
                        [
                            'categoria' => 'incidencia',
                            'titulo' => 'Carga pendiente',
                            'resumen' => 'Otro participante reportó que la carga no estaba preparada.',
                            'mensaje_ids' => [102],
                        ],
                    ]], JSON_THROW_ON_ERROR),
                ]]],
            ]],
            'usageMetadata' => ['promptTokenCount' => 160, 'candidatesTokenCount' => 80],
        ], 200)]);

        $respuesta = app(ClienteGeminiLote::class)->analizar($this->contexto());
        $this->assertCount(2, $respuesta['acontecimientos']);
        $this->assertSame([101], $respuesta['acontecimientos'][0]['mensaje_ids']);
        $this->assertSame([102], $respuesta['acontecimientos'][1]['mensaje_ids']);
        $this->assertSame(160, $respuesta['tokens_entrada']);

        Http::assertSentCount(1);
        Http::assertSent(function (Request $solicitud): bool {
            $cuerpo = $solicitud->data();
            $contenido = json_decode((string) $cuerpo['contents'][0]['parts'][0]['text'], true);
            return is_array($contenido)
                && count($contenido['mensajes']) === 2
                && ! str_contains(json_encode($contenido), 'prueba@ejemplo.com')
                && str_contains(json_encode($contenido), '[correo omitido]')
                && $solicitud->hasHeader('x-goog-api-key', 'clave-ficticia-para-prueba')
                && ! str_contains($solicitud->url(), 'clave-ficticia-para-prueba')
                && $cuerpo['generationConfig']['responseMimeType'] === 'application/json';
        });
    }

    public function test_rechaza_evento_que_cite_mensaje_fuera_del_lote(): void
    {
        config()->set('mensajero.ia.modelo', 'gemini-3.5-flash-lite');
        config()->set('mensajero.ia.clave_api', 'clave-ficticia-para-prueba');

        Http::preventStrayRequests();
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [[
                'finishReason' => 'STOP',
                'content' => ['parts' => [[
                    'text' => json_encode(['acontecimientos' => [[
                        'categoria' => 'logistica',
                        'titulo' => 'Llegada al patio',
                        'resumen' => 'Se reportó llegada al patio por un participante.',
                        'mensaje_ids' => [999],
                    ]]], JSON_THROW_ON_ERROR),
                ]]],
            ]],
        ], 200)]);

        $this->expectException(RuntimeException::class);
        app(ClienteGeminiLote::class)->analizar($this->contexto());
    }

    public function test_descarta_ids_repetidos_antes_de_enviar_a_google(): void
    {
        Http::preventStrayRequests();
        Http::fake();
        $contexto = $this->contexto();
        $contexto[1]['id'] = 101;

        try {
            app(ClienteGeminiLote::class)->analizar($contexto);
            $this->fail('Debió rechazar IDs duplicados.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('inválidos', $error->getMessage());
        }
        Http::assertNothingSent();
    }

    public function test_rechaza_seudonimo_que_contiene_identificador_privado(): void
    {
        Http::preventStrayRequests();
        Http::fake();
        $contexto = $this->contexto();
        $contexto[0]['emisor'] = '5551234567@s.whatsapp.net';

        $this->expectException(RuntimeException::class);
        try {
            app(ClienteGeminiLote::class)->analizar($contexto);
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_no_expone_respuesta_cruda_de_google_ante_error_http(): void
    {
        config()->set('mensajero.ia.modelo', 'gemini-3.5-flash-lite');
        config()->set('mensajero.ia.clave_api', 'clave-ficticia-para-prueba');
        Http::preventStrayRequests();
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'error' => [
                'status' => 'RESOURCE_EXHAUSTED',
                'message' => 'Texto sensible privado@ejemplo.com',
            ],
        ], 429)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HTTP 429; estado RESOURCE_EXHAUSTED');
        app(ClienteGeminiLote::class)->analizar($this->contexto());
    }

    public function test_rechaza_respuestas_parciales(): void
    {
        config()->set('mensajero.ia.modelo', 'gemini-3.5-flash-lite');
        config()->set('mensajero.ia.clave_api', 'clave-ficticia-para-prueba');
        Http::preventStrayRequests();
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [[
                'finishReason' => 'MAX_TOKENS',
                'content' => ['parts' => [['text' => '{}']]],
            ]],
        ], 200)]);

        $this->expectException(RuntimeException::class);
        app(ClienteGeminiLote::class)->analizar($this->contexto());
    }
}
