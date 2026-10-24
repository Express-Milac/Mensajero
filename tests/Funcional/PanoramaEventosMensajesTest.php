<?php

namespace Tests\Funcional;

use App\Models\ChatMonitoreado;
use App\Services\Mensajes\PrepararEventosPanorama;
use Carbon\CarbonImmutable;
use Tests\TestCase;

final class PanoramaEventosMensajesTest extends TestCase
{
    public function test_la_tarjeta_coloca_eventos_a_la_izquierda_y_mensajes_a_la_derecha_con_fotos_privadas(): void
    {
        $grupo = new ChatMonitoreado();
        $grupo->id = 10197;
        $grupo->nombre_interno = 'Logística de prueba';

        $fecha = CarbonImmutable::parse('2026-10-24T12:00:00Z');

        $tarjetas = collect([[
            'grupo' => $grupo,
            'hoy' => 1,
            'ultima' => $fecha,
            'ensayo' => false,
            'eventos' => [[
                'id' => 5,
                'categoria' => 'logistica',
                'titulo' => 'Movimiento de equipos en patio',
                'resumen' => 'Se documenta un movimiento operativo con fotografías.',
                'fecha' => $fecha,
                'evento_origen_id' => 145,
                'fotografias' => 3,
                'documentos' => 1,
                'videos' => 0,
                'audios' => 0,
                'fuentes' => 4,
                'miniaturas' => [
                    ['archivo_id' => 55, 'vinculo' => 'sustento_ia'],
                    ['archivo_id' => 56, 'vinculo' => 'posible_adjunta'],
                ],
                'adjuntos_tentativos' => 2,
            ]],
            'mensajes' => [[
                'id' => 145,
                'remitente' => 'Operador de prueba',
                'telefono_remitente' => null,
                'tiene_remitente' => false,
                'texto' => 'Ingreso a patio',
                'fecha' => $fecha,
            ]],
        ]]);

        $html = view('mensajes.partes.panorama-tarjetas', compact('tarjetas'))->render();

        $izquierda = strpos($html, 'centro-panel-eventos');
        $derecha = strpos($html, 'centro-panel-mensajes');
        $this->assertNotFalse($izquierda);
        $this->assertNotFalse($derecha);
        $this->assertLessThan($derecha, $izquierda);
        $this->assertStringContainsString('Movimiento de equipos en patio', $html);
        $this->assertStringContainsString('3 fotos', $html);
        $this->assertStringContainsString('1 documento', $html);
        $this->assertStringContainsString(route('mensajes.archivo.ver', 55), $html);
        $this->assertStringContainsString('no verificados por IA visual', $html);
        $this->assertStringContainsString('Ver evento y evidencias', $html);
        $this->assertStringContainsString('Ingreso a patio', $html);
        $this->assertStringNotContainsString('Validar interpretación', $html);
    }

    public function test_sin_grupos_no_hace_consultas_y_no_genera_eventos(): void
    {
        $this->assertSame([], app(PrepararEventosPanorama::class)->ejecutar(collect()));
    }
}
