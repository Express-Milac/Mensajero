<?php

namespace Tests\Funcional;

use Tests\TestCase;

/**
 * Verifica que el arranque Laravel 8 conserve la ruta de vida de Mensajero.
 * Sin servicios externos ni acceso a SQL Server.
 */
final class DisponibilidadMensajeroPhp74Test extends TestCase
{
    public function test_disponibilidad_no_expone_datos_operativos(): void
    {
        $respuesta = $this->get('/disponible');
        $respuesta->assertStatus(200);
        $this->assertSame('', $respuesta->getContent());
        $this->assertStringContainsString('no-store', $respuesta->headers->get('Cache-Control'));
    }
}
