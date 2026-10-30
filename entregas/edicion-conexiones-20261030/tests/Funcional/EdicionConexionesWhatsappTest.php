<?php

namespace Tests\Funcional;

use Tests\TestCase;

class EdicionConexionesWhatsappTest extends TestCase
{
    public function test_edicion_y_eliminacion_prohibidas_con_acceso_deshabilitado(): void
    {
        config()->set('mensajero.autenticacion.modo', 'deshabilitado');
        $this->patch('/administracion/whatsapp/conexiones/1', [
            'nombre_visible' => 'Línea autorizada',
        ])->assertStatus(403);
        $this->delete('/administracion/whatsapp/conexiones/1', [
            'confirmacion' => 'ELIMINAR 1',
        ])->assertStatus(403);
    }

    public function test_edicion_y_eliminacion_requieren_sesion(): void
    {
        config()->set('mensajero.autenticacion.modo', 'local');
        $this->patch('/administracion/whatsapp/conexiones/1', [
            'nombre_visible' => 'Línea autorizada',
        ])->assertRedirect(route('acceso'));
        $this->delete('/administracion/whatsapp/conexiones/1', [
            'confirmacion' => 'ELIMINAR 1',
        ])->assertRedirect(route('acceso'));
    }
}