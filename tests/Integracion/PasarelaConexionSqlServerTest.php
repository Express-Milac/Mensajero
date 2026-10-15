<?php

namespace Tests\Integracion;

use App\Models\ChatMonitoreado;
use App\Models\ConexionWhatsapp;
use App\Models\SolicitudPasarela;
use App\Services\Seguridad\ValidadorFirmaPasarela;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PasarelaConexionSqlServerTest extends TestCase
{
    private string $secreto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->secreto = str_repeat('p', 48);
        config([
            'mensajero.pasarela_secreto' => $this->secreto,
            'mensajero.seguridad_pasarela.ventana_segundos' => 300,
        ]);
    }

    public function test_estado_y_lista_autorizada_funcionan_sin_persistir_sondeos_de_lectura(): void
    {
        $this->assertSame('sqlsrv', config('database.default'));
        $this->assertStringEndsWith('_test', config('database.connections.sqlsrv.database'));
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();

        DB::beginTransaction();
        try {
            $sufijo = bin2hex(random_bytes(6));
            $conexion = ConexionWhatsapp::create([
                'clave_conexion' => 'conexion-'.$sufijo,
                'proveedor' => 'web_autohospedado',
                'estado' => 'sin_configurar',
            ]);

            $autorizado = ChatMonitoreado::create([
                'conexion_id' => $conexion->id,
                'id_chat_proveedor' => 'autorizado-'.$sufijo.'@g.us',
                'tipo_chat' => 'grupo',
                'nombre_grupo' => 'Grupo autorizado',
                'es_monitoreado' => true,
                'es_activo' => true,
                'version_autorizacion' => 3,
            ]);
            ChatMonitoreado::create([
                'conexion_id' => $conexion->id,
                'id_chat_proveedor' => 'bloqueado-'.$sufijo.'@g.us',
                'tipo_chat' => 'grupo',
                'nombre_grupo' => 'Grupo no autorizado',
                'es_monitoreado' => false,
                'es_activo' => true,
            ]);

            $this->enviarFirmado('/api/pasarela/v1/estado-conexion', [
                'clave_conexion' => $conexion->clave_conexion,
                'estado' => 'conectado',
                'id_cuenta_externa' => '5214610000000:1@s.whatsapp.net',
            ], 'estado-'.$sufijo)
                ->assertOk()
                ->assertJsonPath('conexion.estado', 'conectado');

            $conexion->refresh();
            $this->assertSame('conectado', $conexion->estado);
            $this->assertNotNull($conexion->conectado_en);
            $this->assertNotNull($conexion->ultimo_latido_en);

            $solicitudesAntes = SolicitudPasarela::count();
            $this->enviarFirmado('/api/pasarela/v1/chats-autorizados', [
                'clave_conexion' => $conexion->clave_conexion,
            ], 'lectura-'.$sufijo)
                ->assertOk()
                ->assertJsonCount(1, 'chats')
                ->assertJsonPath('chats.0.id_chat_proveedor', $autorizado->id_chat_proveedor)
                ->assertJsonPath('chats.0.version_autorizacion', 3);
            $this->assertSame($solicitudesAntes, SolicitudPasarela::count());

            $this->enviarFirmado('/api/pasarela/v1/estado-conexion', [
                'clave_conexion' => $conexion->clave_conexion,
                'estado' => 'desconectado',
            ], 'desconexion-'.$sufijo)
                ->assertOk()
                ->assertJsonPath('conexion.estado', 'desconectado');

            $conexion->refresh();
            $this->assertNotNull($conexion->ultima_desconexion_en);
        } finally {
            DB::rollBack();
        }
    }

    /**
     * @param  array<string, mixed>  $contenido
     */
    private function enviarFirmado(string $ruta, array $contenido, string $identificador): TestResponse
    {
        $cuerpo = json_encode($contenido, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $marcaTiempo = (string) now()->getTimestamp();
        $firma = app(ValidadorFirmaPasarela::class)->generar($identificador, $marcaTiempo, $cuerpo, $this->secreto);

        return $this->call('POST', $ruta, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_MENSAJERO_ID' => $identificador,
            'HTTP_X_MENSAJERO_MARCA_TIEMPO' => $marcaTiempo,
            'HTTP_X_MENSAJERO_FIRMA' => $firma,
        ], $cuerpo);
    }
}
