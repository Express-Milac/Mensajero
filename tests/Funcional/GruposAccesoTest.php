<?php

namespace Tests\Funcional;

use App\Enums\Rol;
use App\Models\Usuario;
use Tests\TestCase;

class GruposAccesoTest extends TestCase
{
    private function usuario(Rol $rol): Usuario
    {
        $usuario = new Usuario(['nombre' => 'Operación', 'email' => 'grupos@example.test']);
        $usuario->id = 7;
        $usuario->rol = $rol;

        return $usuario;
    }

    public function test_modo_sin_autenticacion_no_permite_modificar_lista_permitida(): void
    {
        config(['mensajero.autenticacion.modo' => 'deshabilitado']);

        $this->patch('/grupos/1/monitoreo', ['es_monitoreado' => true])->assertForbidden();
    }

    public function test_visualizador_local_no_puede_modificar_lista_permitida(): void
    {
        config(['mensajero.autenticacion.modo' => 'local']);
        $this->actingAs($this->usuario(Rol::Visualizador));

        $this->patch('/grupos/1/monitoreo', ['es_monitoreado' => true])->assertForbidden();
    }
}
