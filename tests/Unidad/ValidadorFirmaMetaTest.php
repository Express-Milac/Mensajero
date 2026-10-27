<?php

namespace Tests\Unidad;

use App\Services\Meta\ValidadorFirmaMeta;
use PHPUnit\Framework\TestCase;

class ValidadorFirmaMetaTest extends TestCase
{
    public function test_valida_firma_oficial_sobre_el_cuerpo_crudo(): void
    {
        $validador = new ValidadorFirmaMeta;
        $secreto = str_repeat('a', 32);
        $cuerpo = '{"object":"whatsapp_business_account","entry":[]}';
        $firma = $validador->generar($cuerpo, $secreto);

        $this->assertTrue($validador->validar($cuerpo, $firma, $secreto));
        $this->assertFalse($validador->validar($cuerpo.' ', $firma, $secreto));
        $this->assertFalse($validador->validar($cuerpo, 'sha256='.str_repeat('0', 64), $secreto));
        $this->assertFalse($validador->validar($cuerpo, 'firma-invalida', $secreto));
    }
}
