<?php

namespace Tests\Integracion;

use App\Data\WhatsApp\EventoWhatsappEntrante;
use App\Models\ChatMonitoreado;
use App\Models\ConexionWhatsapp;
use App\Models\EventoCrudoWhatsapp;
use App\Models\IdentidadRemitente;
use App\Services\Capture\CapturarEventoWhatsappEntrante;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IdentificacionRemitentesSqlServerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('sqlsrv', config('database.default'));
        $this->assertStringEndsWith('_test', (string) config('database.connections.sqlsrv.database'));
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();

        config([
            'mensajero.autenticacion.modo' => 'deshabilitado',
            'mensajero.consulta_local_sin_autenticacion' => true,
            'mensajero.gestion_local_remitentes_habilitada' => true,
        ]);
    }

    public function test_nombre_interno_vence_pushname_y_se_muestra_en_todos_los_mensajes_sin_alterar_crudos(): void
    {
        DB::beginTransaction();

        try {
            $sufijo = bin2hex(random_bytes(6));
            $conexion = ConexionWhatsapp::query()->create([
                'clave_conexion' => 'remitentes-'.$sufijo,
                'proveedor' => 'recolector_whatsapp',
                'estado' => 'conectado',
            ]);
            $grupo = ChatMonitoreado::query()->create([
                'conexion_id' => $conexion->id,
                'id_chat_proveedor' => 'remitentes-'.$sufijo.'@g.us',
                'nombre_grupo' => 'Grupo identidad '.$sufijo,
                'tipo_chat' => 'grupo',
                'es_activo' => true,
                'es_monitoreado' => true,
            ]);

            $jid = '987654321234567@lid';
            $capturar = app(CapturarEventoWhatsappEntrante::class);
            foreach (['Mensaje original de Laura', 'Segunda respuesta de Laura'] as $indice => $texto) {
                $captura = $capturar->ejecutar(new EventoWhatsappEntrante(
                    conexionId: (int) $conexion->id,
                    chatId: $grupo->id_chat_proveedor,
                    tipoEvento: 'mensaje',
                    tipoMensaje: 'texto',
                    eventoExternoId: 'identidad-'.$sufijo.'-'.$indice,
                    mensajeProveedorId: 'identidad-'.$sufijo.'-'.$indice,
                    contenidoCrudo: json_encode([
                        'key' => ['remoteJid' => $grupo->id_chat_proveedor, 'participant' => $jid],
                        'pushName' => 'Laura WhatsApp',
                        'message' => ['conversation' => $texto],
                    ], JSON_THROW_ON_ERROR),
                    recibidoEn: CarbonImmutable::now('UTC')->subSeconds($indice),
                    versionContrato: 'v3-recolector',
                    remitenteProveedorId: $jid,
                ));
                $this->assertSame('aceptado', $captura->estado);
            }

            $eventos = EventoCrudoWhatsapp::query()
                ->where('chat_monitoreado_id', $grupo->id)
                ->orderBy('id')
                ->get();
            $evento = $eventos->firstOrFail();
            $hashOriginal = $evento->hash_contenido_sha256;

            $this->get('/tablero')->assertOk()->assertSee('Laura WhatsApp');
            $this->get('/mensajes/grupos/'.$grupo->id)->assertOk()->assertSee('Laura WhatsApp');
            $this->get('/mensajes/remitentes')->assertOk()->assertSee('Laura WhatsApp');
            $this->get('/mensajes/remitentes/eventos/'.$evento->id)->assertOk()->assertSee('Laura WhatsApp');

            $this->patch('/mensajes/remitentes/eventos/'.$evento->id, [
                'nombre_interno' => 'Laura Martínez — Tráfico',
            ])->assertRedirect();

            $this->assertDatabaseHas('mensajero_identidades_remitentes', [
                'conexion_id' => $conexion->id,
                'id_remitente_proveedor' => $jid,
                'nombre_interno' => 'Laura Martínez — Tráfico',
            ]);

            $this->get('/tablero')->assertOk()->assertSee('Laura Martínez — Tráfico');
            $this->get('/mensajes/grupos/'.$grupo->id)
                ->assertOk()
                ->assertSee('Laura Martínez — Tráfico');
            $this->get('/mensajes/eventos/'.$eventos->last()->id)
                ->assertOk()
                ->assertSee('Laura Martínez — Tráfico');
            $this->get('/mensajes/remitentes')->assertOk()->assertSee('Laura Martínez — Tráfico');

            $this->assertSame($hashOriginal, $evento->fresh()->hash_contenido_sha256);
            $this->assertSame('capturado', $evento->fresh()->estado_procesamiento);
            $this->assertSame(1, IdentidadRemitente::query()->where('conexion_id', $conexion->id)->count());
        } finally {
            DB::rollBack();
        }
    }

    public function test_sin_permiso_explicito_no_se_permite_cambiar_nombres(): void
    {
        config(['mensajero.gestion_local_remitentes_habilitada' => false]);

        $this->patch('/mensajes/remitentes/eventos/99999', ['nombre_interno' => 'Sin autorización'])
            ->assertForbidden();
    }
}
