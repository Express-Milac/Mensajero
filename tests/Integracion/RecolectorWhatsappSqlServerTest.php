<?php

namespace Tests\Integracion;

use App\Models\ChatMonitoreado;
use App\Models\ConexionWhatsapp;
use App\Models\PeriodoIntegridadWhatsapp;
use App\Models\SolicitudPasarela;
use App\Services\Monitoreo\CambiarMonitoreoChat;
use App\Services\Monitoreo\SincronizarChatsDescubiertos;
use App\Services\Seguridad\ValidadorFirmaPasarela;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class RecolectorWhatsappSqlServerTest extends TestCase
{
    private string $secreto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->secreto = str_repeat('r', 48);
        config([
            'mensajero.pasarela_secreto' => $this->secreto,
            'mensajero.seguridad_pasarela.ventana_segundos' => 300,
        ]);
    }

    public function test_recolector_consulta_lista_actualiza_estado_y_registra_huecos_sin_perdida_silenciosa(): void
    {
        $this->assertSame('sqlsrv', config('database.default'));
        $this->assertStringEndsWith('_test', config('database.connections.sqlsrv.database'));
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();

        DB::beginTransaction();

        try {
            $sufijo = bin2hex(random_bytes(6));
            $conexion = ConexionWhatsapp::query()->create([
                'clave_conexion' => 'recolector-'.$sufijo,
                'proveedor' => 'recolector_whatsapp',
                'estado' => 'sin_configurar',
            ]);

            $idChat = 'grupo-'.$sufijo.'@g.us';
            app(SincronizarChatsDescubiertos::class)->ejecutar($conexion, [[
                'id_chat_proveedor' => $idChat,
                'tipo_chat' => 'grupo',
                'nombre_grupo' => 'Grupo de prueba recolector',
                'es_activo' => true,
            ]]);

            $chat = ChatMonitoreado::query()
                ->where('conexion_id', $conexion->id)
                ->where('id_chat_proveedor', $idChat)
                ->firstOrFail();

            app(CambiarMonitoreoChat::class)->ejecutar(
                (int) $chat->id,
                true,
                'prueba_integracion',
                null,
                'Autorizar grupo del recolector',
            );

            $consultaId = 'consulta-'.$sufijo.'-0001';
            $consulta = ['clave_conexion' => $conexion->clave_conexion];

            $this->enviarFirmado('/api/pasarela/v1/chats-autorizados', $consulta, $consultaId)
                ->assertOk()
                ->assertJsonCount(1, 'chats')
                ->assertJsonPath('chats.0.id_chat_proveedor', $idChat);

            $this->enviarFirmado('/api/pasarela/v1/chats-autorizados', $consulta, $consultaId)
                ->assertOk();

            $this->assertFalse(SolicitudPasarela::query()
                ->where('identificador_solicitud', $consultaId)
                ->exists());

            $estadoId = 'estado-'.$sufijo.'-0001';
            $estado = [
                'clave_conexion' => $conexion->clave_conexion,
                'estado' => 'conectado',
                'id_cuenta_externa' => '521000000000:1@s.whatsapp.net',
                'metadatos' => ['componente' => 'recolector_whatsapp'],
            ];

            $this->enviarFirmado('/api/pasarela/v1/estado-conexion', $estado, $estadoId)
                ->assertOk()
                ->assertJsonPath('conexion.estado', 'conectado');
            $this->enviarFirmado('/api/pasarela/v1/estado-conexion', $estado, $estadoId)
                ->assertOk();

            $conexion->refresh();
            $this->assertSame('conectado', $conexion->estado);
            $this->assertNotNull($conexion->ultimo_latido_en);
            $this->assertFalse(SolicitudPasarela::query()
                ->where('identificador_solicitud', $estadoId)
                ->exists());

            $identificadorPeriodo = hash('sha256', 'periodo-'.$sufijo);
            $integridad = [
                'clave_conexion' => $conexion->clave_conexion,
                'identificador_periodo' => $identificadorPeriodo,
                'inicio_en' => now()->subMinutes(2)->toIso8601String(),
                'fin_en' => now()->subMinute()->toIso8601String(),
                'causa' => 'desconexion_whatsapp',
                'detalles' => ['codigo' => 428],
            ];

            $this->enviarFirmado('/api/pasarela/v1/integridad', $integridad, 'integridad-'.$sufijo.'-0001')
                ->assertOk()
                ->assertJsonPath('existia', false);
            $this->enviarFirmado('/api/pasarela/v1/integridad', $integridad, 'integridad-'.$sufijo.'-0002')
                ->assertOk()
                ->assertJsonPath('existia', true);

            $this->assertSame(1, PeriodoIntegridadWhatsapp::query()
                ->where('conexion_id', $conexion->id)
                ->where('identificador_periodo', $identificadorPeriodo)
                ->count());
            // El interruptor único retira el grupo de la lista autorizada
            // inmediatamente sin borrar sus registros ni la sesión.
            app(CambiarMonitoreoChat::class)->ejecutar((int) $chat->id, false, 'prueba_integracion');
            $this->assertFalse($chat->fresh()->es_visible_tablero);
            $this->enviarFirmado(
                '/api/pasarela/v1/chats-autorizados',
                $consulta,
                'consulta-apagada-'.$sufijo
            )->assertOk()->assertJsonCount(0, 'chats');

            app(CambiarMonitoreoChat::class)->ejecutar((int) $chat->id, true, 'prueba_integracion');
            $this->assertTrue($chat->fresh()->es_visible_tablero);
            $this->enviarFirmado(
                '/api/pasarela/v1/chats-autorizados',
                $consulta,
                'consulta-encendida-'.$sufijo
            )->assertOk()->assertJsonCount(1, 'chats');

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
        $firma = app(ValidadorFirmaPasarela::class)->generar(
            $identificador,
            $marcaTiempo,
            $cuerpo,
            $this->secreto,
        );

        return $this->call('POST', $ruta, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_MENSAJERO_ID' => $identificador,
            'HTTP_X_MENSAJERO_MARCA_TIEMPO' => $marcaTiempo,
            'HTTP_X_MENSAJERO_FIRMA' => $firma,
        ], $cuerpo);
    }
}
