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
        $this->patch('/operacion/1/revision', ['decision' => 'validado'])->assertNotFound();
    }

    public function test_tablero_ia_no_pide_aprobacion_incluso_con_permisos_locales(): void
    {
        config([
            'mensajero.autenticacion.modo' => 'deshabilitado',
            'mensajero.consulta_local_sin_autenticacion' => true,
            'mensajero.gestion_local_operacion_habilitada' => true,
            'mensajero.ia.grupos_autorizados' => [],
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->get('/operacion')
            ->assertOk()
            ->assertSee('Bitácora de acontecimientos')
            ->assertDontSee('Validar interpretación')
            ->assertDontSee('Descartar interpretación')
            ->assertDontSee('Por verificar');
    }

    public function test_no_existe_un_procedimiento_de_aprobacion_humana(): void
    {
        config([
            'mensajero.autenticacion.modo' => 'deshabilitado',
            'mensajero.consulta_local_sin_autenticacion' => true,
            'mensajero.gestion_local_operacion_habilitada' => false,
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->patch('/operacion/1/revision', ['decision' => 'validado'])
            ->assertNotFound();
    }

    public function test_el_acceso_remoto_permanece_cerrado_para_la_bitacora(): void
    {
        config([
            'mensajero.autenticacion.modo' => 'deshabilitado',
            'mensajero.consulta_local_sin_autenticacion' => true,
            'mensajero.gestion_local_operacion_habilitada' => true,
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.42'])
            ->patch('/operacion/1/revision', ['decision' => 'validado'])
            ->assertNotFound();
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.42'])
            ->get('/operacion')->assertForbidden();
    }
}
