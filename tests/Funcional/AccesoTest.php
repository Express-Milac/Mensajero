<?php

namespace Tests\Funcional;

use App\Enums\Rol;
use App\Models\Usuario;
use App\Services\DiagnosticoInfraestructura;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AccesoTest extends TestCase
{
    private function usuario(Rol $rol): Usuario
    {
        $usuario = new Usuario(['nombre' => 'Operación', 'email' => 'prueba@example.test']);
        $usuario->id = 1;
        $usuario->rol = $rol;

        return $usuario;
    }

    public function test_autenticacion_deshabilitada_exige_permisos_de_consulta_y_no_expone_el_tablero(): void
    {
        config([
            'mensajero.autenticacion.modo' => 'deshabilitado',
            'mensajero.consulta_local_sin_autenticacion' => false,
        ]);

        $this->get('/tablero')->assertForbidden();
        $this->get('/acceso')->assertRedirect('/tablero');
    }

    public function test_autenticacion_deshabilitada_no_expone_diagnostico_administrativo(): void
    {
        config(['mensajero.autenticacion.modo' => 'deshabilitado']);
        $this->get('/estado')->assertForbidden();
    }

    public function test_autenticacion_corporativa_falla_cerrado_hasta_tener_adaptador(): void
    {
        config(['mensajero.autenticacion.modo' => 'corporativo']);
        $this->get('/tablero')->assertStatus(503);
    }

    public function test_modo_local_impide_tablero_y_diagnostico_a_invitados(): void
    {
        config(['mensajero.autenticacion.modo' => 'local']);
        $this->get('/tablero')->assertRedirect('/acceso');
        $this->get('/estado')->assertRedirect('/acceso');
        $this->get('/acceso')
            ->assertOk()
            ->assertSee('Entrar al sistema')
            ->assertSee('Mensajero MILAC')
            ->assertSee('css/panorama-claro.css')
            ->assertSee('css/catalogo-grupos.css')
            ->assertSee('css/interfaz-unificada.css')
            ->assertSee('class="panorama-claro pagina-acceso"', false)
            ->assertDontSee('Gestor de grupos')
            ->assertDontSee('Remitentes');
    }

    public function test_visualizador_local_no_puede_abrir_apartado_tecnico(): void
    {
        config(['mensajero.autenticacion.modo' => 'local']);
        $this->actingAs($this->usuario(Rol::Visualizador));
        $this->get('/mensajes/tecnico')->assertForbidden();
        $this->get('/estado')->assertForbidden();
        $this->assertFalse(Gate::allows('revisar-eventos'));
        $this->assertFalse(Gate::allows('administrar'));
    }

    public function test_supervisor_local_puede_revisar_pero_no_administrar(): void
    {
        config(['mensajero.autenticacion.modo' => 'local']);
        $this->actingAs($this->usuario(Rol::Supervisor));
        $this->assertTrue(Gate::allows('revisar-eventos'));
        $this->assertFalse(Gate::allows('administrar'));
    }

    public function test_administrador_local_ve_diagnostico_degradado(): void
    {
        config(['mensajero.autenticacion.modo' => 'local']);
        $this->mock(DiagnosticoInfraestructura::class)->shouldReceive('comprobar')->once()->andReturn(['almacenamiento' => 'fallo']);
        $this->actingAs($this->usuario(Rol::Administrador))->get('/estado')->assertStatus(503)->assertJsonPath('comprobaciones.almacenamiento', 'fallo');
    }

    public function test_acceso_local_invalido_es_generico_y_limitado(): void
    {
        config(['mensajero.autenticacion.modo' => 'local']);
        Auth::shouldReceive('guard')->andReturnSelf();
        Auth::shouldReceive('check')->andReturn(false);
        Auth::shouldReceive('attempt')->times(5)->andReturn(false);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/acceso', ['email' => 'desconocido@example.test', 'password' => 'incorrecta'])->assertSessionHasErrors('email');
        }

        $this->post('/acceso', ['email' => 'desconocido@example.test', 'password' => 'incorrecta'])->assertStatus(429);
    }

    public function test_salida_local_elimina_autenticacion(): void
    {
        config(['mensajero.autenticacion.modo' => 'local']);
        $this->actingAs($this->usuario(Rol::Visualizador))->post('/salir')->assertRedirect('/acceso');
        $this->assertGuest();
    }

    public function test_rol_no_puede_asignarse_masivamente(): void
    {
        $usuario = $this->usuario(Rol::Visualizador);
        $usuario->fill(['rol' => 'administrador']);
        $this->assertSame(Rol::Visualizador, $usuario->rol);
    }
}
