<?php

namespace Tests\Unidad;

use App\Services\IA\AgruparMensajesGemini;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AgruparMensajesGeminiTest extends TestCase
{
    private function mensaje(int $id, int $grupo, string $hora, string $texto = 'Reporte'): array
    {
        return [
            'id' => $id,
            'grupo_id' => $grupo,
            'recibido_en' => new DateTimeImmutable('2026-10-26T'.$hora.'-06:00'),
            'texto' => $texto,
        ];
    }

    public function test_nunca_mezcla_grupos_aunque_lleguen_simultaneamente(): void
    {
        $lotes = (new AgruparMensajesGemini)->agrupar([
            $this->mensaje(3, 10196, '12:00:20'),
            $this->mensaje(2, 10197, '12:00:10'),
            $this->mensaje(1, 10196, '12:00:00'),
        ]);
        $this->assertSame([[1], [2], [3]], $lotes);
    }

    public function test_limita_seis_mensajes_y_preserva_orden(): void
    {
        $mensajes = [];
        for ($i = 8; $i >= 1; $i--) {
            $mensajes[] = $this->mensaje($i, 10196, '12:00:'.sprintf('%02d', $i));
        }
        $this->assertSame([[1, 2, 3, 4, 5, 6], [7, 8]], (new AgruparMensajesGemini)->agrupar($mensajes));
    }

    public function test_separa_mensajes_con_pausas_superiores_a_noventa_segundos(): void
    {
        $this->assertSame([[1, 2], [3]], (new AgruparMensajesGemini)->agrupar([
            $this->mensaje(1, 10196, '12:00:00'),
            $this->mensaje(2, 10196, '12:01:30'),
            $this->mensaje(3, 10196, '12:03:01'),
        ]));
    }

    public function test_separa_ventanas_superiores_a_cinco_minutos(): void
    {
        $this->assertSame([[1, 2, 3, 4], [5]], (new AgruparMensajesGemini)->agrupar([
            $this->mensaje(1, 10196, '12:00:00'),
            $this->mensaje(2, 10196, '12:01:20'),
            $this->mensaje(3, 10196, '12:02:40'),
            $this->mensaje(4, 10196, '12:04:00'),
            $this->mensaje(5, 10196, '12:05:20'),
        ]));
    }

    public function test_rechaza_mensajes_duplicados(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new AgruparMensajesGemini)->agrupar([
            $this->mensaje(1, 10196, '12:00:00'),
            $this->mensaje(1, 10196, '12:00:01'),
        ]);
    }

    public function test_rechaza_mensajes_sin_texto_analizable(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new AgruparMensajesGemini)->agrupar([$this->mensaje(1, 10196, '12:00:00', '  ')]);
    }
}
