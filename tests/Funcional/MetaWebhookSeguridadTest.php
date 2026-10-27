<?php

namespace Tests\Funcional;

use App\Services\Meta\ValidadorFirmaMeta;
use Tests\TestCase;

class MetaWebhookSeguridadTest extends TestCase
{
    private string $secreto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->secreto = str_repeat('m', 32);

        config([
            'mensajero.meta_whatsapp.secreto_aplicacion' => $this->secreto,
            'mensajero.meta_whatsapp.token_verificacion' => 'token-verificacion-meta-123456',
        ]);
    }

    public function test_meta_puede_verificar_el_webhook_con_el_token_configurado(): void
    {
        $this->get('/api/meta/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=token-verificacion-meta-123456&hub.challenge=987654')
            ->assertOk()
            ->assertSeeText('987654');
    }

    public function test_verificacion_rechaza_un_token_incorrecto(): void
    {
        $this->get('/api/meta/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=incorrecto&hub.challenge=987654')
            ->assertForbidden();
    }

    public function test_post_falla_cerrado_si_no_hay_secreto_de_aplicacion(): void
    {
        config(['mensajero.meta_whatsapp.secreto_aplicacion' => null]);

        $this->postJson('/api/meta/whatsapp/webhook', [
            'object' => 'whatsapp_business_account',
            'entry' => [],
        ])->assertStatus(503);
    }

    public function test_post_rechaza_firma_incorrecta_y_acepta_firma_oficial_valida(): void
    {
        $cuerpo = json_encode([
            'object' => 'whatsapp_business_account',
            'entry' => [],
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $this->call('POST', '/api/meta/whatsapp/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.str_repeat('0', 64),
        ], $cuerpo)->assertUnauthorized();

        $firma = app(ValidadorFirmaMeta::class)->generar($cuerpo, $this->secreto);

        $this->call('POST', '/api/meta/whatsapp/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $firma,
        ], $cuerpo)
            ->assertOk()
            ->assertJsonPath('estado', 'aceptado');
    }
}
