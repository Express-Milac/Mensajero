<?php

namespace Tests\Unidad;

use App\Services\Seguridad\PrepararContenidoEvidencia;
use PHPUnit\Framework\TestCase;

class PrepararContenidoEvidenciaTest extends TestCase
{
    public function test_oculta_secretos_e_identificadores_anidados_y_conserva_el_texto(): void
    {
        $original = json_encode([
            'key' => [
                'id' => 'evento-ejemplo',
                'remoteJid' => 'grupo-interno@g.us',
                'participant' => '5219999999999@s.whatsapp.net',
                'participantPn' => '5218888888888@s.whatsapp.net',
            ],
            'message' => [
                'conversation' => 'z93',
                'messageContextInfo' => [
                    'messageSecret' => 'SECRETO_NO_MOSTRAR',
                    'nested' => [
                        'mediaKey' => 'CLAVE_MULTIMEDIA_NO_MOSTRAR',
                        'url' => 'https://ejemplo.test/privado',
                    ],
                ],
            ],
            'messageTimestamp' => 1792429200,
        ], JSON_THROW_ON_ERROR);

        $res = (new PrepararContenidoEvidencia)->ejecutar($original);

        $this->assertFalse($res['truncado']);
        $this->assertStringContainsString('z93', $res['contenido']);
        $this->assertStringContainsString('evento-ejemplo', $res['contenido']);
        $this->assertStringContainsString(PrepararContenidoEvidencia::MARCA_OCULTA, $res['contenido']);
        $this->assertStringNotContainsString('SECRETO_NO_MOSTRAR', $res['contenido']);
        $this->assertStringNotContainsString('CLAVE_MULTIMEDIA_NO_MOSTRAR', $res['contenido']);
        $this->assertStringNotContainsString('5219999999999', $res['contenido']);
        $this->assertStringNotContainsString('5218888888888', $res['contenido']);
        $this->assertStringNotContainsString('ejemplo.test/privado', $res['contenido']);
        $this->assertSame('z93', json_decode($original, true, 512, JSON_THROW_ON_ERROR)['message']['conversation']);
    }

    public function test_json_invalido_no_se_publica_sin_filtrado(): void
    {
        $res = (new PrepararContenidoEvidencia)->ejecutar('SECRETO_NO_MOSTRAR{"sin cerrar":');

        $this->assertStringNotContainsString('SECRETO_NO_MOSTRAR', $res['contenido']);
        $this->assertStringContainsString('no disponible', $res['contenido']);
    }

    public function test_contenido_grande_no_muestra_fragmentos_inseguros(): void
    {
        $res = (new PrepararContenidoEvidencia)->ejecutar(
            '{"messageSecret":"SECRETO_NO_MOSTRAR","datos":"'.str_repeat('A', 200).'"}',
            100,
        );

        $this->assertTrue($res['truncado']);
        $this->assertStringNotContainsString('SECRETO_NO_MOSTRAR', $res['contenido']);
        $this->assertStringContainsString('omitida', $res['contenido']);
    }
}
