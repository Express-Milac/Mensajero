<?php

namespace Tests\Integracion;

use App\Data\WhatsApp\EventoWhatsappEntrante;
use App\Models\ChatMonitoreado;
use App\Models\ConexionWhatsapp;
use App\Models\PeriodoIntegridadWhatsapp;
use App\Services\Capture\CapturarEventoWhatsappEntrante;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VistaMensajesSqlServerTest extends TestCase
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
        ]);
    }

    public function test_consulta_local_muestra_grupo_y_evidencia_escapada_sin_normalizar_ni_modificar_datos(): void
    {
        DB::beginTransaction();

        try {
            $sufijo = bin2hex(random_bytes(6));
            $conexion = ConexionWhatsapp::query()->create([
                'clave_conexion' => 'vista-'.$sufijo,
                'proveedor' => 'recolector_whatsapp',
                'estado' => 'conectado',
            ]);
            $grupo = ChatMonitoreado::query()->create([
                'conexion_id' => $conexion->id,
                'id_chat_proveedor' => 'vista-'.$sufijo.'@g.us',
                'nombre_grupo' => 'Grupo de prueba visual '.$sufijo,
                'tipo_chat' => 'grupo',
                'es_monitoreado' => true,
                'es_activo' => true,
            ]);

            $contenido = '<script>alert(1)</script>';
            $captura = app(CapturarEventoWhatsappEntrante::class)->ejecutar(new EventoWhatsappEntrante(
                conexionId: (int) $conexion->id,
                chatId: $grupo->id_chat_proveedor,
                tipoEvento: 'mensaje',
                tipoMensaje: 'texto',
                eventoExternoId: 'vista-evento-'.$sufijo,
                mensajeProveedorId: 'vista-evento-'.$sufijo,
                contenidoCrudo: json_encode([
                    'message' => ['conversation' => 'Llegó '.$contenido],
                ], JSON_THROW_ON_ERROR),
                recibidoEn: CarbonImmutable::now('UTC'),
                versionContrato: 'v3-recolector',
                remitenteProveedorId: '5219990000000@s.whatsapp.net',
            ));

            $this->assertSame('aceptado', $captura->estado);

            PeriodoIntegridadWhatsapp::query()->create([
                'conexion_id' => $conexion->id,
                'identificador_periodo' => hash('sha256', 'intervalo-vista-'.$sufijo),
                'inicio_en' => CarbonImmutable::parse('2026-10-19T11:00:00Z'),
                'fin_en' => CarbonImmutable::parse('2026-10-19T11:00:05Z'),
                'causa' => 'desconexion_whatsapp',
            ]);

            $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
                ->get('/mensajes')
                ->assertOk()
                ->assertSee('Grupo de prueba visual '.$sufijo);

            $this->get('/mensajes/grupos/'.$grupo->id)
                ->assertOk()
                ->assertSee('Llegó '.$contenido)
                ->assertDontSee($contenido, false)
                ->assertSee('Ver evidencia')
                ->assertSee('Registro de conectividad')
                ->assertSee('1 intervalo registrado')
                ->assertSee('Desconexión de WhatsApp')
                ->assertSee('Estado «Capturado»')
                ->assertDontSee('Integridad pendiente de conciliación');

            $this->get('/mensajes/eventos/'.$captura->eventoCrudoId)
                ->assertOk()
                ->assertSee('v3-recolector')
                ->assertSee('Contenido crudo')
                ->assertDontSee($contenido, false);

            $this->assertDatabaseHas('mensajero_eventos_crudos_whatsapp', [
                'id' => $captura->eventoCrudoId,
                'estado_procesamiento' => 'capturado',
            ]);
            $this->assertDatabaseMissing('mensajero_mensajes_whatsapp', [
                'evento_crudo_id' => $captura->eventoCrudoId,
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_un_grupo_no_autorizado_no_expone_evidencia_previamente_capturada(): void
    {
        DB::beginTransaction();

        try {
            $sufijo = bin2hex(random_bytes(6));
            $conexion = ConexionWhatsapp::query()->create([
                'clave_conexion' => 'grupo-vista-'.$sufijo,
                'proveedor' => 'recolector_whatsapp',
                'estado' => 'conectado',
            ]);

            $grupo = ChatMonitoreado::query()->create([
                'conexion_id' => $conexion->id,
                'id_chat_proveedor' => 'grupo-privado-'.$sufijo.'@g.us',
                'tipo_chat' => 'grupo',
                'es_monitoreado' => false,
                'es_activo' => true,
            ]);

            $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
                ->get('/mensajes/grupos/'.$grupo->id)
                ->assertNotFound();
        } finally {
            DB::rollBack();
        }
    }
}
