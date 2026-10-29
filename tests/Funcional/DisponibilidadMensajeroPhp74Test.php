<?php

namespace Tests\Funcional;

use Tests\TestCase;

/**
 * Verifica que el arranque Laravel 8 conserve la ruta de vida de Mensajero.
 * Sin servicios externos ni acceso a SQL Server.
 */
final class DisponibilidadMensajeroPhp74Test extends TestCase
{
    public function test_modo_corporativo_sin_integracion_rechaza_acceso_sin_consultar_sql(): void
    {
        config(['mensajero.autenticacion.modo' => 'corporativo']);

        $this->get('/tablero')->assertStatus(503);
        $this->get('/grupos')->assertStatus(503);
    }

    public function test_el_enrutador_del_servidor_integrado_existe(): void
    {
        $this->assertFileExists(base_path('server.php'));
    }

    public function test_disponibilidad_no_expone_datos_operativos(): void
    {
        $respuesta = $this->get('/disponible');
        $respuesta->assertStatus(200);
        $this->assertSame('', $respuesta->getContent());
        $this->assertStringContainsString('no-store', $respuesta->headers->get('Cache-Control'));
    }
}
