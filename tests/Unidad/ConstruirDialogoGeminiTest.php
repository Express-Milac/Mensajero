<?php

namespace Tests\Unidad;

use App\Models\MensajeWhatsapp;
use App\Services\IA\ConstruirDialogoGemini;
use App\Services\IA\PrepararContextoGemini;
use PHPUnit\Framework\TestCase;

final class ConstruirDialogoGeminiTest extends TestCase
{
    public function test_conserva_los_emisores_anonimos_y_referencias_explicitas_sin_telefono(): void
    {
        $primero = new MensajeWhatsapp([
            'id' => 101,
            'tipo_mensaje' => 'texto',
            'contenido_texto' => '¿Entró la unidad ficticia?',
            'contacto_remitente_id' => 321,
        ]);
        $respuesta = new MensajeWhatsapp([
            'id' => 102,
            'tipo_mensaje' => 'texto',
            'contenido_texto' => 'Sí, entró. Llámame al +52 461 123 4567',
            'contacto_remitente_id' => 322,
            'mensaje_citado_id' => 101,
        ]);
        $foto = new MensajeWhatsapp([
            'id' => 103,
            'tipo_mensaje' => 'foto',
            'pie_multimedia' => 'Adjunto evidencia de entrada',
            'contacto_remitente_id' => 322,
        ]);

        $contexto = (new ConstruirDialogoGemini(new PrepararContextoGemini))
            ->ejecutar([$primero, $respuesta, $foto]);

        $this->assertCount(3, $contexto);
        $this->assertSame('participante_1', $contexto[0]['emisor']);
        $this->assertSame('participante_2', $contexto[1]['emisor']);
        $this->assertSame('participante_2', $contexto[2]['emisor']);
        $this->assertSame(101, $contexto[1]['responde_a']);
        $this->assertNull($contexto[2]['responde_a']);
        $this->assertSame('leyenda_foto', $contexto[2]['tipo']);
        $this->assertStringNotContainsString('461', $contexto[1]['texto']);
        $this->assertStringNotContainsString('321', json_encode($contexto, JSON_THROW_ON_ERROR));
    }

    public function test_una_cita_fuera_del_lote_no_autoriza_recuperar_contenido_previo(): void
    {
        $mensaje = new MensajeWhatsapp([
            'id' => 203, 'tipo_mensaje' => 'texto',
            'contenido_texto' => 'Queda pendiente confirmar.',
            'mensaje_citado_id' => 150,
        ]);

        $contexto = (new ConstruirDialogoGemini(new PrepararContextoGemini))->ejecutar([$mensaje]);
        $this->assertNull($contexto[0]['responde_a']);
        $this->assertSame('participante_desconocido_1', $contexto[0]['emisor']);
    }
}
