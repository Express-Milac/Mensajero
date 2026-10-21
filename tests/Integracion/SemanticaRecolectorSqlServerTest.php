<?php

namespace Tests\Integracion;

use App\Models\ChatMonitoreado;
use App\Models\ConexionWhatsapp;
use App\Models\EventoCrudoWhatsapp;
use App\Services\Monitoreo\CambiarMonitoreoChat;
use App\Services\Monitoreo\SincronizarChatsDescubiertos;
use App\Services\Seguridad\ValidadorFirmaPasarela;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SemanticaRecolectorSqlServerTest extends TestCase
{
    private string $secreto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->secreto = str_repeat('s', 48);
        config([
            'mensajero.pasarela_secreto' => $this->secreto,
            'mensajero.seguridad_pasarela.ventana_segundos' => 300,
        ]);
    }

    public function test_conserva_relaciones_semanticas_y_no_colisiona_ediciones_con_el_mensaje_original(): void
    {
        $this->assertSame('sqlsrv', config('database.default'));
        $this->assertStringEndsWith('_test', config('database.connections.sqlsrv.database'));
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();

        DB::beginTransaction();

        try {
            [$conexion, $chat] = $this->crearGrupoAutorizado();
            $idObjetivo = 'MENSAJE-OBJETIVO-'.$conexion->id;
            $remitente = '521000000000@s.whatsapp.net';

            $original = $this->eventoBase($conexion, $chat, [
                'tipo_evento' => 'mensaje',
                'tipo_mensaje' => 'texto',
                'evento_externo_id' => $idObjetivo,
                'mensaje_proveedor_id' => $idObjetivo,
                'id_remitente_proveedor' => $remitente,
                'nombre_remitente' => 'Rosa - Operaciones',
                'fuente_nombre_remitente' => 'contacto',
                'telefono_remitente' => '521000000000',
                'contenido_crudo' => '{"conversation":"texto original"}',
            ]);

            $this->enviarFirmado('/api/pasarela/v1/eventos', $original, 'semantica-original-'.$conexion->id)
                ->assertOk()
                ->assertJsonPath('estado', 'aceptado');

            $edicionUno = $this->eventoBase($conexion, $chat, [
                'tipo_evento' => 'edicion',
                'tipo_mensaje' => 'texto',
                'evento_externo_id' => $idObjetivo,
                'mensaje_proveedor_id' => $idObjetivo,
                'id_remitente_proveedor' => $remitente,
                'id_mensaje_objetivo_proveedor' => $idObjetivo,
                'contenido_crudo' => '{"protocolMessage":{"type":14,"editedMessage":{"conversation":"texto corregido uno"}}}',
            ]);

            $respuestaEdicion = $this->enviarFirmado(
                '/api/pasarela/v1/eventos',
                $edicionUno,
                'semantica-edicion-uno-'.$conexion->id,
            );
            $respuestaEdicion->assertOk()->assertJsonPath('estado', 'aceptado');

            $edicionDos = $edicionUno;
            $edicionDos['contenido_crudo'] = '{"protocolMessage":{"type":14,"editedMessage":{"conversation":"texto corregido dos"}}}';

            $this->enviarFirmado('/api/pasarela/v1/eventos', $edicionDos, 'semantica-edicion-dos-'.$conexion->id)
                ->assertOk()
                ->assertJsonPath('estado', 'aceptado');

            $this->enviarFirmado('/api/pasarela/v1/eventos', $edicionUno, 'semantica-edicion-repetida-'.$conexion->id)
                ->assertOk()
                ->assertJsonPath('estado', 'duplicado');

            $eliminacion = $this->eventoBase($conexion, $chat, [
                'tipo_evento' => 'eliminacion',
                'tipo_mensaje' => 'eliminacion',
                'evento_externo_id' => $idObjetivo,
                'mensaje_proveedor_id' => $idObjetivo,
                'id_remitente_proveedor' => $remitente,
                'id_mensaje_objetivo_proveedor' => $idObjetivo,
                'contenido_crudo' => '{"protocolMessage":{"type":0}}',
            ]);

            $this->enviarFirmado('/api/pasarela/v1/eventos', $eliminacion, 'semantica-eliminacion-'.$conexion->id)
                ->assertOk()
                ->assertJsonPath('estado', 'aceptado');

            $respuesta = $this->eventoBase($conexion, $chat, [
                'tipo_evento' => 'mensaje',
                'tipo_mensaje' => 'texto',
                'evento_externo_id' => 'RESPUESTA-'.$conexion->id,
                'mensaje_proveedor_id' => 'RESPUESTA-'.$conexion->id,
                'id_remitente_proveedor' => $remitente,
                'id_mensaje_citado_proveedor' => $idObjetivo,
                'contenido_crudo' => '{"extendedTextMessage":{"text":"respuesta"}}',
            ]);

            $this->enviarFirmado('/api/pasarela/v1/eventos', $respuesta, 'semantica-respuesta-'.$conexion->id)
                ->assertOk()
                ->assertJsonPath('estado', 'aceptado');

            $eventosObjetivo = EventoCrudoWhatsapp::query()
                ->where('conexion_id', $conexion->id)
                ->where('id_evento_externo', $idObjetivo)
                ->orderBy('id')
                ->get();

            $this->assertCount(4, $eventosObjetivo);
            $this->assertSame(
                ['mensaje', 'edicion', 'edicion', 'eliminacion'],
                $eventosObjetivo->pluck('tipo_evento')->all(),
            );
            $this->assertSame(
                ['texto', 'texto', 'texto', 'eliminacion'],
                $eventosObjetivo->pluck('tipo_mensaje')->all(),
            );
            $this->assertSame($remitente, $eventosObjetivo[0]->id_remitente_proveedor);
            $this->assertSame('Rosa - Operaciones', $eventosObjetivo[0]->nombre_remitente);
            $this->assertSame('contacto', $eventosObjetivo[0]->fuente_nombre_remitente);
            $this->assertSame('521000000000', $eventosObjetivo[0]->telefono_remitente);
            $this->assertSame('{"conversation":"texto original"}', $eventosObjetivo[0]->contenido);
            $this->assertSame(
                hash('sha256', '{"conversation":"texto original"}'),
                $eventosObjetivo[0]->hash_contenido_sha256,
            );
            $this->assertSame($idObjetivo, $eventosObjetivo[1]->id_mensaje_objetivo_proveedor);
            $this->assertSame($idObjetivo, $eventosObjetivo[2]->id_mensaje_objetivo_proveedor);
            $this->assertSame($idObjetivo, $eventosObjetivo[3]->id_mensaje_objetivo_proveedor);
            $this->assertSame('v3-recolector', $eventosObjetivo[1]->version_contrato);
            $this->assertNull($eventosObjetivo[1]->nombre_remitente);
            $this->assertNull($eventosObjetivo[1]->telefono_remitente);

            $respuestaGuardada = EventoCrudoWhatsapp::query()
                ->where('conexion_id', $conexion->id)
                ->where('id_evento_externo', $respuesta['evento_externo_id'])
                ->firstOrFail();

            $this->assertSame('texto', $respuestaGuardada->tipo_mensaje);
            $this->assertSame($idObjetivo, $respuestaGuardada->id_mensaje_citado_proveedor);
            $this->assertSame($remitente, $respuestaGuardada->id_remitente_proveedor);
        } finally {
            DB::rollBack();
        }
    }

    /**
     * @param  array<string, mixed>  $cambios
     * @return array<string, mixed>
     */
    private function eventoBase(ConexionWhatsapp $conexion, ChatMonitoreado $chat, array $cambios): array
    {
        return array_merge([
            'clave_conexion' => $conexion->clave_conexion,
            'chat_id' => $chat->id_chat_proveedor,
            'tipo_evento' => 'mensaje',
            'tipo_mensaje' => 'texto',
            'evento_externo_id' => null,
            'mensaje_proveedor_id' => null,
            'id_remitente_proveedor' => null,
            'id_mensaje_citado_proveedor' => null,
            'id_mensaje_objetivo_proveedor' => null,
            'contenido_crudo' => '{}',
            'recibido_en' => now('UTC')->toIso8601String(),
            'version_contrato' => 'v3-recolector',
            'multimedia' => null,
        ], $cambios);
    }

    /**
     * @return array{0: ConexionWhatsapp, 1: ChatMonitoreado}
     */
    private function crearGrupoAutorizado(): array
    {
        $sufijo = bin2hex(random_bytes(6));
        $conexion = ConexionWhatsapp::query()->create([
            'clave_conexion' => 'semantica-'.$sufijo,
            'proveedor' => 'recolector_whatsapp',
            'estado' => 'conectado',
        ]);
        $idChat = 'grupo-semantica-'.$sufijo.'@g.us';

        app(SincronizarChatsDescubiertos::class)->ejecutar($conexion, [[
            'id_chat_proveedor' => $idChat,
            'tipo_chat' => 'grupo',
            'nombre_grupo' => 'Grupo semántica',
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
            'Autorizar semántica',
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
