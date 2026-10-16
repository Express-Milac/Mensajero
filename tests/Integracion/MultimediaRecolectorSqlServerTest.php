<?php

namespace Tests\Integracion;

use App\Models\ArchivoWhatsapp;
use App\Models\ChatMonitoreado;
use App\Models\ConexionWhatsapp;
use App\Models\EventoCrudoWhatsapp;
use App\Services\Monitoreo\CambiarMonitoreoChat;
use App\Services\Monitoreo\SincronizarChatsDescubiertos;
use App\Services\Seguridad\ValidadorFirmaPasarela;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class MultimediaRecolectorSqlServerTest extends TestCase
{
    private string $secreto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->secreto = str_repeat('m', 48);
        config([
            'mensajero.pasarela_secreto' => $this->secreto,
            'mensajero.seguridad_pasarela.ventana_segundos' => 300,
        ]);
        Storage::fake('mensajero_local');
    }

    public function test_recolector_registra_ruta_hash_y_metadatos_sin_guardar_binario_en_sql(): void
    {
        $this->assertSame('sqlsrv', config('database.default'));
        $this->assertStringEndsWith('_test', config('database.connections.sqlsrv.database'));
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();

        DB::beginTransaction();

        try {
            [$conexion, $chat] = $this->crearGrupoAutorizado();
            $contenidoArchivo = 'contenido-binario-de-prueba';
            $identificadorArchivo = hash('sha256', 'archivo-'.$conexion->clave_conexion);
            $ruta = sprintf(
                'multimedia/whatsapp/%s/2026/10/%s.pdf',
                $conexion->clave_conexion,
                $identificadorArchivo,
            );
            Storage::disk('mensajero_local')->put($ruta, $contenidoArchivo);

            $evento = [
                'clave_conexion' => $conexion->clave_conexion,
                'chat_id' => $chat->id_chat_proveedor,
                'tipo_evento' => 'mensaje',
                'tipo_mensaje' => 'documento',
                'evento_externo_id' => 'evento-'.$conexion->clave_conexion,
                'mensaje_proveedor_id' => 'mensaje-'.$conexion->clave_conexion,
                'contenido_crudo' => '{"documentMessage":{"mimetype":"application/pdf"}}',
                'recibido_en' => now('UTC')->toIso8601String(),
                'version_contrato' => 'v2-recolector',
                'multimedia' => [
                    'identificador_archivo' => $identificadorArchivo,
                    'ruta_relativa' => $ruta,
                    'hash_sha256' => hash('sha256', $contenidoArchivo),
                    'tamano_bytes' => strlen($contenidoArchivo),
                    'tipo_mime' => 'application/pdf',
                    'nombre_original' => 'reporte.pdf',
                    'extension' => 'pdf',
                ],
            ];

            $respuesta = $this->enviarFirmado(
                '/api/pasarela/v1/eventos',
                $evento,
                'multimedia-'.$conexion->clave_conexion,
            );
            $respuesta->assertOk()->assertJsonPath('estado', 'aceptado');

            $crudo = EventoCrudoWhatsapp::query()
                ->where('conexion_id', $conexion->id)
                ->where('id_evento_externo', $evento['evento_externo_id'])
                ->firstOrFail();
            $archivo = ArchivoWhatsapp::query()
                ->where('evento_crudo_id', $crudo->id)
                ->firstOrFail();

            $this->assertSame($ruta, $archivo->ruta_relativa);
            $this->assertSame(hash('sha256', $contenidoArchivo), $archivo->hash_sha256);
            $this->assertSame(strlen($contenidoArchivo), $archivo->tamano_bytes);
            $this->assertSame('mensajero_local', $archivo->disco);
            $this->assertSame('verificado', $archivo->estado);
            $this->assertFalse(array_key_exists('contenido_binario', $archivo->getAttributes()));

            $eventoInvalido = $evento;
            $eventoInvalido['evento_externo_id'] = 'evento-invalido-'.$conexion->clave_conexion;
            $eventoInvalido['mensaje_proveedor_id'] = 'mensaje-invalido-'.$conexion->clave_conexion;
            $eventoInvalido['multimedia']['identificador_archivo'] = hash('sha256', 'archivo-invalido-'.$conexion->clave_conexion);
            $eventoInvalido['multimedia']['ruta_relativa'] = sprintf(
                'multimedia/whatsapp/%s/2026/10/%s.pdf',
                $conexion->clave_conexion,
                $eventoInvalido['multimedia']['identificador_archivo'],
            );
            Storage::disk('mensajero_local')->put(
                $eventoInvalido['multimedia']['ruta_relativa'],
                $contenidoArchivo,
            );
            $eventoInvalido['multimedia']['hash_sha256'] = str_repeat('0', 64);

            $this->enviarFirmado(
                '/api/pasarela/v1/eventos',
                $eventoInvalido,
                'multimedia-invalida-'.$conexion->clave_conexion,
            )->assertUnprocessable();

            $this->assertFalse(EventoCrudoWhatsapp::query()
                ->where('conexion_id', $conexion->id)
                ->where('id_evento_externo', $eventoInvalido['evento_externo_id'])
                ->exists());
        } finally {
            DB::rollBack();
        }
    }

    /**
     * @return array{0: ConexionWhatsapp, 1: ChatMonitoreado}
     */
    private function crearGrupoAutorizado(): array
    {
        $sufijo = bin2hex(random_bytes(6));
        $conexion = ConexionWhatsapp::query()->create([
            'clave_conexion' => 'recolector-'.$sufijo,
            'proveedor' => 'recolector_whatsapp',
            'estado' => 'conectado',
        ]);
        $idChat = 'grupo-'.$sufijo.'@g.us';

        app(SincronizarChatsDescubiertos::class)->ejecutar($conexion, [[
            'id_chat_proveedor' => $idChat,
            'tipo_chat' => 'grupo',
            'nombre_grupo' => 'Grupo multimedia',
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
            'Autorizar multimedia',
        );

        return [$conexion, $chat];
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
