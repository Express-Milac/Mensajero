<?php

namespace Tests\Unidad;

use App\Services\Seguridad\ValidadorFirmaPasarela;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class ValidadorFirmaPasarelaTest extends TestCase
{
    private ValidadorFirmaPasarela $validador;

    private string $secreto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validador = new ValidadorFirmaPasarela;
        $this->secreto = str_repeat('s', 32);
    }

    public function test_firma_correcta_es_valida(): void
    {
        $ahora = CarbonImmutable::parse('2026-10-15T22:00:00Z');
        $marca = (string) $ahora->getTimestamp();
        $cuerpo = '{"evento":"prueba"}';
        $identificador = 'solicitud-1234567890';
        $firma = $this->validador->generar($identificador, $marca, $cuerpo, $this->secreto);

        $this->assertTrue($this->validador->validar($identificador, $marca, $cuerpo, $firma, $this->secreto, 300, $ahora));
    }

    public function test_cuerpo_alterado_invalida_firma(): void
    {
        $ahora = CarbonImmutable::parse('2026-10-15T22:00:00Z');
        $marca = (string) $ahora->getTimestamp();
        $identificador = 'solicitud-1234567890';
        $firma = $this->validador->generar($identificador, $marca, '{"valor":1}', $this->secreto);

        $this->assertFalse($this->validador->validar($identificador, $marca, '{"valor":2}', $firma, $this->secreto, 300, $ahora));
    }

    public function test_marca_fuera_de_ventana_es_rechazada(): void
    {
        $ahora = CarbonImmutable::parse('2026-10-15T22:00:00Z');
        $marca = (string) $ahora->subSeconds(301)->getTimestamp();
        $identificador = 'solicitud-1234567890';
        $cuerpo = '{}';
        $firma = $this->validador->generar($identificador, $marca, $cuerpo, $this->secreto);

        $this->assertFalse($this->validador->validar($identificador, $marca, $cuerpo, $firma, $this->secreto, 300, $ahora));
    }

    public function test_identificador_y_firma_deben_tener_formato_valido(): void
    {
        $ahora = CarbonImmutable::parse('2026-10-15T22:00:00Z');
        $marca = (string) $ahora->getTimestamp();

        $this->assertFalse($this->validador->validar('corto', $marca, '{}', 'invalida', $this->secreto, 300, $ahora));
    }
}
