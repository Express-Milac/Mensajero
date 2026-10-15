<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Usuario;
use App\Services\SaludInfraestructura;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AccessTest extends TestCase
{
    private function usuario(Rol $rol): Usuario
    {
        $usuario = new Usuario(['nombre' => 'Operación', 'correo' => 'prueba@example.test']);
        $usuario->id = 1;
        $usuario->rol = $rol;

        return $usuario;
    }

    public function test_modo_sin_autenticacion_muestra_tablero_sin_usuario(): void
    {
        config(['mensajero.autenticacion.modo' => 'deshabilitado']);

        $this->get('/tablero')
            ->assertOk()
            ->assertSee('Acceso interno temporal sin autenticación')
            ->assertSee('WhatsApp Business');

        $this->get('/acceso')->assertRedirect('/tablero');
    }

    public function test_sin_autenticacion_bloquea_administracion_fuera_de_ip_permitida(): void
    {
        config([
            'mensajero.autenticacion.modo' => 'deshabilitado',
            'mensajero.autenticacion.ips_administracion' => ['127.0.0.1'],
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '10.20.30.40'])
            ->get('/salud')
            ->assertForbidden();
    }

    public function test_sin_autenticacion_permite_salud_desde_ip_administrativa(): void
    {
        config([
            'mensajero.autenticacion.modo' => 'deshabilitado',
            'mensajero.autenticacion.ips_administracion' => ['127.0.0.1'],
        ]);
        $this->mock(SaludInfraestructura::class)
            ->shouldReceive('verificar')
            ->once()
            ->andReturn(['aplicacion' => 'correcto']);

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->get('/salud')
            ->assertOk()
            ->assertJsonPath('comprobaciones.aplicacion', 'correcto');
    }

    public function test_modo_corporativo_falla_cerrado_hasta_existir_adaptador(): void
    {
        config(['mensajero.autenticacion.modo' => 'corporativo']);
        $this->get('/tablero')->assertStatus(503);
    }

    public function test_modo_local_redirige_invitados_al_acceso(): void
    {
        config(['mensajero.autenticacion.modo' => 'local']);
        $this->get('/tablero')->assertRedirect('/acceso');
        $this->get('/salud')->assertRedirect('/acceso');
        $this->get('/acceso')->assertOk()->assertSee('Entrar al sistema');
    }

    public function test_visualizador_local_no_puede_administrar(): void
    {
        config(['mensajero.autenticacion.modo' => 'local']);
        $this->actingAs($this->usuario(Rol::Visualizador));

        $this->get('/tablero')->assertOk();
        $this->get('/salud')->assertForbidden();
        $this->assertFalse(Gate::allows('revisar-eventos'));
    }

    public function test_supervisor_local_puede_revisar_pero_no_administrar(): void
    {
        config(['mensajero.autenticacion.modo' => 'local']);
        $this->actingAs($this->usuario(Rol::Supervisor));

        $this->assertTrue(Gate::allows('revisar-eventos'));
        $this->assertFalse(Gate::allows('administrar'));
    }

    public function test_administrador_local_ve_salud_degradada(): void
    {
        config(['mensajero.autenticacion.modo' => 'local']);
        $this->mock(SaludInfraestructura::class)
            ->shouldReceive('verificar')
            ->once()
            ->andReturn(['almacenamiento' => 'fallo']);

        $this->actingAs($this->usuario(Rol::Administrador))
            ->get('/salud')
            ->assertStatus(503)
            ->assertJsonPath('comprobaciones.almacenamiento', 'fallo');
    }

    public function test_acceso_local_invalido_es_generico_y_limitado(): void
    {
        config(['mensajero.autenticacion.modo' => 'local']);
        Auth::shouldReceive('attempt')->times(5)->andReturn(false);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/acceso', ['correo' => 'desconocido@example.test', 'clave' => 'incorrecta'])
                ->assertSessionHasErrors('correo');
        }

        $this->post('/acceso', ['correo' => 'desconocido@example.test', 'clave' => 'incorrecta'])
            ->assertStatus(429);
    }

    public function test_rol_no_puede_asignarse_masivamente(): void
    {
        $usuario = $this->usuario(Rol::Visualizador);
        $usuario->fill(['rol' => 'administrador']);
        $this->assertSame(Rol::Visualizador, $usuario->rol);
    }
}
