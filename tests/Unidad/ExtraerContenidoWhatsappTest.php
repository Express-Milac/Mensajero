<?php

namespace Tests\Unidad;

use App\Services\Normalizacion\ExtraerContenidoWhatsapp;
use PHPUnit\Framework\TestCase;

class ExtraerContenidoWhatsappTest extends TestCase
{
    public function test_extrae_texto_de_conversacion_y_marca_proveedor(): void
    {
        $resultado = (new ExtraerContenidoWhatsapp)->ejecutar(json_encode([
            'key' => ['id' => 'mensaje-prueba'],
            'message' => ['conversation' => 'La unidad salió'],
            'messageTimestamp' => 1792429200,
        ], JSON_THROW_ON_ERROR));

        $this->assertSame('La unidad salió', $resultado['texto']);
        $this->assertNull($resultado['pie_multimedia']);
        $this->assertSame(0, $resultado['enviado_en']->getOffset());
    }

    public function test_extrae_cita_envuelta_y_pie_de_multimedia(): void
    {
        $resultado = (new ExtraerContenidoWhatsapp)->ejecutar(json_encode([
            'message' => [
                'ephemeralMessage' => [
                    'message' => ['extendedTextMessage' => ['text' => 'respuesta']],
                ],
            ],
        ], JSON_THROW_ON_ERROR));
        $this->assertSame('respuesta', $resultado['texto']);

        $foto = (new ExtraerContenidoWhatsapp)->ejecutar(json_encode([
            'message' => ['imageMessage' => ['caption' => 'foto de entrega']],
        ], JSON_THROW_ON_ERROR));

        $this->assertNull($foto['texto']);
        $this->assertSame('foto de entrega', $foto['pie_multimedia']);
        $this->assertNull($foto['enviado_en']);
    }

    public function test_no_inventa_texto_o_fechas_para_tipos_desconocidos(): void
    {
        $resultado = (new ExtraerContenidoWhatsapp)->ejecutar(json_encode([
            'message' => ['locationMessage' => ['degreesLatitude' => 20.5]],
            'messageTimestamp' => 1,
        ], JSON_THROW_ON_ERROR));

        $this->assertNull($resultado['texto']);
        $this->assertNull($resultado['pie_multimedia']);
        $this->assertNull($resultado['enviado_en']);
    }

    public function test_rechaza_json_corrupto_sin_perder_evidencia(): void
    {
        $this->expectException(\JsonException::class);

        (new ExtraerContenidoWhatsapp)->ejecutar('{texto inválido');
    }
}
