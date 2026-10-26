<?php

namespace Tests\Unidad;

use App\Services\IA\ValidarAcontecimientosLoteGemini;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ValidarAcontecimientosLoteGeminiTest extends TestCase
{
    private function evento(string $titulo, array $ids): array
    {
        return [
            'categoria' => 'logistica',
            'titulo' => $titulo,
            'resumen' => 'El remitente reportó una actualización operativa aún no corroborada.',
            'mensaje_ids' => $ids,
        ];
    }

    public function test_permite_varios_acontecimientos_independientes_con_sustento(): void
    {
        $resultado = (new ValidarAcontecimientosLoteGemini)->validar([
            'acontecimientos' => [
                $this->evento('Unidad llega a patio', [1, 2]),
                $this->evento('Carga pendiente en almacén', [3]),
            ],
        ], [1, 2, 3]);
        $this->assertCount(2, $resultado);
        $this->assertSame([1, 2], $resultado[0]['mensaje_ids']);
        $this->assertSame([3], $resultado[1]['mensaje_ids']);
    }

    public function test_cero_acontecimientos_es_respuesta_valida(): void
    {
        $this->assertSame([], (new ValidarAcontecimientosLoteGemini)->validar([
            'acontecimientos' => [],
        ], [1]));
    }

    public function test_rechaza_referencias_a_mensajes_no_incluidos_en_el_lote(): void
    {
        $this->expectException(RuntimeException::class);
        (new ValidarAcontecimientosLoteGemini)->validar([
            'acontecimientos' => [$this->evento('Unidad llega a patio', [1, 999])],
        ], [1, 2]);
    }

    public function test_rechaza_acontecimientos_repetidos_con_las_mismas_fuentes(): void
    {
        $this->expectException(RuntimeException::class);
        (new ValidarAcontecimientosLoteGemini)->validar([
            'acontecimientos' => [
                $this->evento('Unidad llega a patio', [1]),
                $this->evento('Unidad llega a patio', [1]),
            ],
        ], [1, 2]);
    }

    public function test_rechaza_datos_privados_dentro_del_resumen(): void
    {
        $this->expectException(RuntimeException::class);
        $evento = $this->evento('Llegada reportada', [1]);
        $evento['resumen'] = 'El operador notificó a privado@ejemplo.com que llegó.';
        (new ValidarAcontecimientosLoteGemini)->validar([
            'acontecimientos' => [$evento],
        ], [1]);
    }

    public function test_rechaza_mas_de_seis_mensajes_por_lote(): void
    {
        $this->expectException(RuntimeException::class);
        (new ValidarAcontecimientosLoteGemini)->validar([
            'acontecimientos' => [],
        ], [1, 2, 3, 4, 5, 6, 7]);
    }
}
