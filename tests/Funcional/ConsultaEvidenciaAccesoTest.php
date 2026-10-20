<?php

namespace Tests\Funcional;

use Tests\TestCase;

class ConsultaEvidenciaAccesoTest extends TestCase
{
    public function test_las_conversaciones_no_son_publicas_por_defecto_sin_inicio_de_sesion(): void
    {
        config([
            'mensajero.autenticacion.modo' => 'deshabilitado',
            'mensajero.consulta_local_sin_autenticacion' => false,
        ]);

        $this->get('/tablero')->assertForbidden();
        $this->get('/mensajes')->assertForbidden();
        $this->get('/mensajes/tecnico')->assertForbidden();
        $this->get('/mensajes/grupos/10020')->assertForbidden();
        $this->get('/mensajes/eventos/11070')->assertForbidden();
    }

    public function test_una_direccion_remota_no_puede_acceder_aunque_el_poc_local_este_habilitado(): void
    {
        config([
            'mensajero.autenticacion.modo' => 'deshabilitado',
            'mensajero.consulta_local_sin_autenticacion' => true,
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.47'])
            ->get('/mensajes')
            ->assertForbidden();
    }

    public function test_el_modo_corporativo_no_implementado_falla_cerrado(): void
    {
        config(['mensajero.autenticacion.modo' => 'corporativo']);

        $this->get('/mensajes')->assertStatus(503);
    }
}
