<?php

namespace Tests\Integracion;

use App\Models\ChatMonitoreado;
use App\Models\ConexionWhatsapp;
use App\Models\EventoCrudoWhatsapp;
use App\Models\SolicitudPasarela;
use App\Services\Monitoreo\CambiarMonitoreoChat;
use App\Services\Seguridad\ValidadorFirmaPasarela;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PasarelaSeguraSqlServerTest extends TestCase
{
    private string $secreto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->secreto = str_repeat('k', 48);
        config([
            'mensajero.pasarela_secreto' => $this->secreto,
            'mensajero.seguridad_pasarela.ventana_segundos' => 300,
        ]);
    }

    public function test_firma_repeticion_lista_permitida_y_captura_funcionan_en_sql_server(): void
    {
        $this->assertSame('sqlsrv', config('database.default'));
        $this->assertStringEndsWith('_test', config('database.connections.sqlsrv.database'));
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();

        DB::beginTransaction();
        try {
            $sufijo = bin2hex(random_bytes(6));
            $conexion = ConexionWhatsapp::create([
                'clave_conexion' => 'pasarela-'.$sufijo,
                'proveedor' => 'web_autohospedado',
                'estado' => 'conectado',
            ]);

            $idChat = 'grupo-'.$sufijo.'@g.us';
            $descubrimiento = [
                'clave_conexion' => $conexion->clave_conexion,
                'chats' => [[
                    'id_chat_proveedor' => $idChat,
                    'tipo_chat' => 'grupo',
                    'nombre_grupo' => 'Grupo de integración',
                    'es_activo' => true,
                ]],
            ];

            $idDescubrimiento = 'descubrimiento-'.$sufijo.'-0001';
            $this->enviarFirmado('/api/pasarela/v1/chats-descubiertos', $descubrimiento, $idDescubrimiento)
                ->assertOk()
                ->assertJsonPath('estado', 'aceptado')
                ->assertJsonPath('resultado.creados', 1);

            $chat = ChatMonitoreado::query()
                ->where('conexion_id', $conexion->id)
                ->where('id_chat_proveedor', $idChat)
                ->firstOrFail();

            $this->assertFalse($chat->es_monitoreado);
            $this->assertSame(0, $chat->version_autorizacion);
            $this->assertTrue(SolicitudPasarela::query()->where('identificador_solicitud', $idDescubrimiento)->exists());

            $this->enviarFirmado('/api/pasarela/v1/chats-descubiertos', $descubrimiento, $idDescubrimiento)
                ->assertStatus(409)
                ->assertJsonPath('mensaje', 'Solicitud de pasarela repetida.');

            $idFirmaIncorrecta = 'firma-incorrecta-'.$sufijo;
            $this->enviarFirmado('/api/pasarela/v1/chats-descubiertos', $descubrimiento, $idFirmaIncorrecta, false)
                ->assertUnauthorized();
            $this->assertFalse(SolicitudPasarela::query()->where('identificador_solicitud', $idFirmaIncorrecta)->exists());

            $idCaducada = 'firma-caducada-'.$sufijo;
            $this->enviarFirmado('/api/pasarela/v1/chats-descubiertos', $descubrimiento, $idCaducada, true, -301)
                ->assertUnauthorized();
            $this->assertFalse(SolicitudPasarela::query()->where('identificador_solicitud', $idCaducada)->exists());

            $evento = [
                'clave_conexion' => $conexion->clave_conexion,
                'chat_id' => $idChat,
                'tipo_evento' => 'mensaje',
                'tipo_mensaje' => 'texto',
                'evento_externo_id' => 'evento-'.$sufijo.'-001',
                'mensaje_proveedor_id' => 'mensaje-'.$sufijo.'-001',
                'contenido_crudo' => '{"texto":"Unidad lista"}',
                'recibido_en' => now()->toIso8601String(),
                'version_contrato' => 'v1',
            ];

            $this->enviarFirmado('/api/pasarela/v1/eventos', $evento, 'evento-no-autorizado-'.$sufijo)
                ->assertOk()
                ->assertJsonPath('estado', 'ignorado')
                ->assertJsonPath('disposicion', 'ignorar_no_monitoreado');
            $this->assertSame(0, EventoCrudoWhatsapp::query()->where('conexion_id', $conexion->id)->count());

            app(CambiarMonitoreoChat::class)->ejecutar((int) $chat->id, true, 'prueba_integracion', null, 'Autorizar prueba de pasarela');

            $idEvento = 'evento-autorizado-'.$sufijo;
            $this->enviarFirmado('/api/pasarela/v1/eventos', $evento, $idEvento)
                ->assertOk()
                ->assertJsonPath('estado', 'aceptado')
                ->assertJsonPath('disposicion', 'aceptar');
            $this->assertSame(1, EventoCrudoWhatsapp::query()->where('conexion_id', $conexion->id)->count());

            $this->enviarFirmado('/api/pasarela/v1/eventos', $evento, $idEvento)
                ->assertStatus(409);
            $this->assertSame(1, EventoCrudoWhatsapp::query()->where('conexion_id', $conexion->id)->count());

            $this->enviarFirmado('/api/pasarela/v1/eventos', $evento, 'evento-dominio-duplicado-'.$sufijo)
                ->assertOk()
                ->assertJsonPath('estado', 'duplicado');
            $this->assertSame(1, EventoCrudoWhatsapp::query()->where('conexion_id', $conexion->id)->count());
        } finally {
            DB::rollBack();
        }
    }

    /**
     * @param  array<string, mixed>  $contenido
     */
    private function enviarFirmado(
        string $ruta,
        array $contenido,
        string $identificador,
        bool $firmaValida = true,
        int $desfaseSegundos = 0
    ): TestResponse {
        $cuerpo = json_encode($contenido, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $marcaTiempo = (string) (now()->getTimestamp() + $desfaseSegundos);
        $firma = app(ValidadorFirmaPasarela::class)->generar($identificador, $marcaTiempo, $cuerpo, $this->secreto);

        if (! $firmaValida) {
            $firma = 'sha256='.str_repeat('0', 64);
        }

        return $this->call('POST', $ruta, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_MENSAJERO_ID' => $identificador,
            'HTTP_X_MENSAJERO_MARCA_TIEMPO' => $marcaTiempo,
            'HTTP_X_MENSAJERO_FIRMA' => $firma,
        ], $cuerpo);
    }
}
