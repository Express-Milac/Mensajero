<?php

namespace Tests\Integracion;

use App\Models\ChatMonitoreado;
use App\Models\ConexionWhatsapp;
use App\Models\EventoCrudoWhatsapp;
use App\Services\Meta\ValidadorFirmaMeta;
use App\Services\Monitoreo\CambiarMonitoreoChat;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class MetaWebhookSqlServerTest extends TestCase
{
    private string $secreto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->secreto = str_repeat('z', 32);

        config([
            'mensajero.meta_whatsapp.secreto_aplicacion' => $this->secreto,
            'mensajero.meta_whatsapp.token_verificacion' => 'token-integracion-meta-12345',
        ]);
    }

    public function test_webhook_oficial_descubre_grupo_respeta_lista_permitida_e_idempotencia(): void
    {
        $this->assertSame('sqlsrv', config('database.default'));
        $this->assertStringEndsWith('_test', config('database.connections.sqlsrv.database'));
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();

        DB::beginTransaction();

        try {
            $sufijo = bin2hex(random_bytes(5));
            $idNumero = '900'.random_int(100000000, 999999999);
            $idGrupo = 'grupo-meta-'.$sufijo;

            $conexion = ConexionWhatsapp::query()->create([
                'clave_conexion' => 'meta-'.$sufijo,
                'proveedor' => 'meta_cloud_api',
                'id_cuenta_externa' => $idNumero,
                'estado' => 'configurado',
                'metadatos' => ['waba_id' => 'waba-'.$sufijo],
            ]);

            $primero = $this->payload(
                $idNumero,
                $idGrupo,
                'wamid.'.$sufijo.'.001',
                'text',
                ['text' => ['body' => 'Primera prueba oficial']],
            );

            $this->enviarFirmado($primero)
                ->assertOk()
                ->assertJsonPath('resultado.ignorados', 1);

            $chat = ChatMonitoreado::query()
                ->where('conexion_id', $conexion->id)
                ->where('id_chat_proveedor', $idGrupo)
                ->firstOrFail();

            $this->assertFalse($chat->es_monitoreado);
            $this->assertSame(0, EventoCrudoWhatsapp::query()->where('conexion_id', $conexion->id)->count());

            app(CambiarMonitoreoChat::class)->ejecutar(
                (int) $chat->id,
                true,
                'prueba_integracion',
                null,
                'Autorizar grupo oficial de prueba',
            );

            $segundo = $this->payload(
                $idNumero,
                $idGrupo,
                'wamid.'.$sufijo.'.002',
                'text',
                ['text' => ['body' => 'Mensaje autorizado']],
            );

            $this->enviarFirmado($segundo)
                ->assertOk()
                ->assertJsonPath('resultado.aceptados', 1);

            $this->assertSame(1, EventoCrudoWhatsapp::query()->where('conexion_id', $conexion->id)->count());

            $this->enviarFirmado($segundo)
                ->assertOk()
                ->assertJsonPath('resultado.duplicados', 1);

            $this->assertSame(1, EventoCrudoWhatsapp::query()->where('conexion_id', $conexion->id)->count());

            $pegatina = $this->payload(
                $idNumero,
                $idGrupo,
                'wamid.'.$sufijo.'.003',
                'sticker',
                ['sticker' => ['id' => 'media-'.$sufijo]],
            );

            $this->enviarFirmado($pegatina)
                ->assertOk()
                ->assertJsonPath('resultado.ignorados', 1);

            $this->assertSame(1, EventoCrudoWhatsapp::query()->where('conexion_id', $conexion->id)->count());
        } finally {
            DB::rollBack();
        }
    }

    /**
     * @param  array<string, mixed>  $contenido
     * @return array<string, mixed>
     */
    private function payload(
        string $idNumero,
        string $idGrupo,
        string $idMensaje,
        string $tipo,
        array $contenido
    ): array {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => 'waba-prueba',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => [
                            'display_phone_number' => '524610000000',
                            'phone_number_id' => $idNumero,
                        ],
                        'contacts' => [[
                            'profile' => ['name' => 'Operador de prueba'],
                            'wa_id' => '524611111111',
                        ]],
                        'messages' => [[
                            'from' => '524611111111',
                            'id' => $idMensaje,
                            'timestamp' => (string) now()->getTimestamp(),
                            'group_id' => $idGrupo,
                            'type' => $tipo,
                            ...$contenido,
                        ]],
                    ],
                ]],
            ]],
        ];
    }

    /**
     * @param  array<string, mixed>  $contenido
     */
    private function enviarFirmado(array $contenido): TestResponse
    {
        $cuerpo = json_encode(
            $contenido,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
        $firma = app(ValidadorFirmaMeta::class)->generar($cuerpo, $this->secreto);

        return $this->call('POST', '/api/meta/whatsapp/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $firma,
        ], $cuerpo);
    }
}
