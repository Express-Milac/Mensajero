<?php

namespace Tests\Integracion;

use App\Enums\Rol;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AutenticacionSqlServerTest extends TestCase
{
    public function test_migraciones_acceso_local_y_salida_en_sql_server(): void
    {
        config(['mensajero.autenticacion.modo' => 'local']);
        $this->assertSame('sqlsrv', config('database.default'));
        $this->assertStringEndsWith('_test', config('database.connections.sqlsrv.database'), 'Use una base desechable cuyo nombre termine en _test.');
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();

        DB::beginTransaction();
        try {
            $usuario = new Usuario(['name' => 'Integración', 'email' => 'integracion-'.bin2hex(random_bytes(6)).'@example.test', 'password' => 'Prueba-segura-1234!']);
            $usuario->rol = Rol::Visualizador;
            $usuario->save();

            $this->post('/acceso', ['email' => $usuario->email, 'password' => 'Prueba-segura-1234!'])->assertRedirect('/tablero');
            $this->assertAuthenticatedAs($usuario);
            $this->get('/estado')->assertForbidden();
            $this->post('/salir')->assertRedirect('/acceso');
            $this->assertGuest();
        } finally {
            DB::rollBack();
        }
    }
}
