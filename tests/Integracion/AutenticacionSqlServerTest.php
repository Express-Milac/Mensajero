<?php

namespace Tests\Integracion;

use App\Enumeraciones\Rol;
use App\Modelos\Usuario;
use Illuminate\Support\Facades\DB;
use Tests\CasoPrueba;

class AutenticacionSqlServerTest extends CasoPrueba
{
    public function test_autenticacion_local_opcional_funciona_sobre_tabla_usuarios_en_espanol(): void
    {
        $this->assertSame('sqlsrv', config('database.default'));
        $this->assertStringEndsWith('_test', config('database.connections.sqlsrv.database'), 'Use una base desechable terminada en _test.');
        config(['mensajero.autenticacion.modo' => 'local']);
        $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();

        DB::beginTransaction();
        try {
            $usuario = new Usuario([
                'nombre' => 'Integración',
                'correo' => 'integracion-'.bin2hex(random_bytes(6)).'@example.test',
                'clave' => 'Clave-segura-1234!',
            ]);
            $usuario->rol = Rol::Visualizador;
            $usuario->save();

            $this->post('/acceso', [
                'correo' => $usuario->correo,
                'clave' => 'Clave-segura-1234!',
            ])->assertRedirect('/tablero');

            $this->assertAuthenticatedAs($usuario);
            $this->get('/salud')->assertForbidden();
            $this->post('/salir')->assertRedirect('/acceso');
            $this->assertGuest();
        } finally {
            DB::rollBack();
        }
    }
}
