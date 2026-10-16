<?php

namespace Tests\Unidad;

use App\Services\Meta\ClienteMetaWhatsapp;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ClienteMetaWhatsappTest extends TestCase
{
    public function test_consulta_graph_api_directamente_sin_intermediarios(): void
    {
        config([
            'mensajero.meta_whatsapp.version_graph' => 'v26.0',
            'mensajero.meta_whatsapp.token_acceso' => 'token-oficial-prueba',
            'mensajero.meta_whatsapp.id_numero' => '123456789',
        ]);

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'id' => '123456789',
                'display_phone_number' => '+52 461 000 0000',
            ]),
        ]);

        $respuesta = app(ClienteMetaWhatsapp::class)->obtenerInformacionNumero();

        $this->assertSame('123456789', $respuesta['id']);

        Http::assertSent(function (Request $request): bool {
            return str_starts_with(
                $request->url(),
                'https://graph.facebook.com/v26.0/123456789',
            ) && $request->hasHeader('Authorization', 'Bearer token-oficial-prueba');
        });
    }
}
