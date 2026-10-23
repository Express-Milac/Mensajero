<?php

namespace Tests\Funcional;

use Tests\TestCase;

final class OperacionAccesoTest extends TestCase
{
    public function test_la_cara_operativa_no_es_publica_sin_autorizacion_de_lectura(): void
    {
        config([
            'mensajero.autenticacion.modo' => 'deshabilitado',
            'mensajero.consulta_local_sin_autenticacion' => false,
        ]);

        $this->get('/operacion')->assertForbidden();
        $this->patch('/operacion/1/revision', ['decision' => 'validado'])->assertForbidden();
    }

    public function test_una_autorizacion_de_consulta_no_permite_validar_sin_permiso_independiente(): void
    {
        config([
            'mensajero.autenticacion.modo' => 'deshabilitado',
            'mensajero.consulta_local_sin_autenticacion' => true,
            'mensajero.gestion_local_operacion_habilitada' => false,
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->patch('/operacion/1/revision', ['decision' => 'validado'])
            ->assertForbidden();
    }

    public function test_el_permiso_de_ensayo_local_no_funciona_en_red_remota(): void
    {
        config([
            'mensajero.autenticacion.modo' => 'deshabilitado',
            'mensajero.consulta_local_sin_autenticacion' => true,
            'mensajero.gestion_local_operacion_habilitada' => true,
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.42'])
            ->patch('/operacion/1/revision', ['decision' => 'validado'])
            ->assertForbidden();
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.42'])
            ->get('/operacion')->assertForbidden();
    }
}
