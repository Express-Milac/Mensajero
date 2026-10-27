<?php

namespace Tests\Integracion;

use App\Data\WhatsApp\EventoWhatsappEntrante;
use App\Jobs\NormalizarEventoWhatsappTrabajo;
use App\Models\ChatMonitoreado;
use App\Models\ConexionWhatsapp;
use App\Models\EventoCrudoWhatsapp;
use App\Models\MensajeBuzonSalida;
use App\Services\Capture\CapturarEventoWhatsappEntrante;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ProteccionDespachoSqlServerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('sqlsrv', config('database.default'));
        $this->assertStringEndsWith('_test', (string) config('database.connections.sqlsrv.database'));
        config(['mensajero.normalizacion.habilitada' => false]);
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
    }

    public function test_rechaza_despacho_general_y_limites_no_controlados(): void
    {
        $this->assertSame(1, Artisan::call('mensajero:despachar-buzon'));
        $this->assertSame(1, Artisan::call('mensajero:despachar-buzon', [
            '--evento-id' => 123,
        ]));
        $this->assertSame(1, Artisan::call('mensajero:despachar-buzon', [
            '--evento-id' => 123,
            '--limite' => 2,
        ]));
    }

    public function test_envia_unicamente_el_evento_autorizado_con_cola_simulada(): void
    {
        DB::beginTransaction();

        try {
            $grupo = $this->crearGrupo();
            $seleccionado = $this->crearEvento($grupo);
            $otro = $this->crearEvento($grupo);
            $cola = $this->colaDelGrupo($grupo);
            Queue::fake();

            $codigo = Artisan::call('mensajero:despachar-buzon', [
                '--evento-id' => $seleccionado->id,
                '--limite' => 1,
                '--cola-ensayo' => $cola,
            ]);

            $this->assertSame(0, $codigo, Artisan::output());
            Queue::assertPushedOn($cola, NormalizarEventoWhatsappTrabajo::class);

            $this->assertNotNull(MensajeBuzonSalida::query()
                ->where('entidad_id', $seleccionado->id)
                ->firstOrFail()->enviado_en);
            $this->assertNull(MensajeBuzonSalida::query()
                ->where('entidad_id', $otro->id)
                ->firstOrFail()->enviado_en);
            $this->assertSame('capturado', $seleccionado->fresh()->estado_procesamiento);
            $this->assertSame('capturado', $otro->fresh()->estado_procesamiento);
        } finally {
            DB::rollBack();
        }
    }

    public function test_rechaza_grupo_no_autorizado_sin_publicar(): void
    {
        DB::beginTransaction();

        try {
            $grupo = $this->crearGrupo();
            $evento = $this->crearEvento($grupo);
            $cola = $this->colaDelGrupo($grupo);
            $grupo->update(['es_monitoreado' => false]);

            Queue::fake();

            $this->assertSame(1, Artisan::call('mensajero:despachar-buzon', [
                '--evento-id' => $evento->id,
                '--limite' => 1,
                '--cola-ensayo' => $cola,
            ]));
            Queue::assertNothingPushed();
            $this->assertNull(MensajeBuzonSalida::query()
                ->where('entidad_id', $evento->id)
                ->firstOrFail()->enviado_en);
        } finally {
            DB::rollBack();
        }
    }

    public function test_rechaza_base_de_produccion_antes_de_consultar_evento(): void
    {
        $original = config('database.connections.sqlsrv.database');

        try {
            config(['database.connections.sqlsrv.database' => 'milacdb']);

            $this->assertSame(1, Artisan::call('mensajero:despachar-buzon', [
                '--evento-id' => 1,
                '--limite' => 1,
                '--cola-ensayo' => 'mensajero_ensayo_0123456789abcdef',
            ]));
        } finally {
            config(['database.connections.sqlsrv.database' => $original]);
        }
    }

    public function test_rechaza_cola_distinta_de_la_asignada_al_evento(): void
    {
        DB::beginTransaction();

        try {
            $grupo = $this->crearGrupo();
            $evento = $this->crearEvento($grupo);
            Queue::fake();

            $this->assertSame(1, Artisan::call('mensajero:despachar-buzon', [
                '--evento-id' => $evento->id,
                '--limite' => 1,
                '--cola-ensayo' => 'mensajero_ensayo_0123456789abcdef',
            ]));

            Queue::assertNothingPushed();
            $this->assertNull(MensajeBuzonSalida::query()
                ->where('entidad_id', $evento->id)
                ->firstOrFail()->enviado_en);
        } finally {
            DB::rollBack();
        }
    }

    private function colaDelGrupo(ChatMonitoreado $grupo): string
    {
        return str_replace('ensayo-redis-', 'mensajero_ensayo_', $grupo->conexion->clave_conexion);
    }

    private function crearGrupo(): ChatMonitoreado
    {
        $sufijo = bin2hex(random_bytes(8));

        $conexion = ConexionWhatsapp::query()->create([
            'clave_conexion' => 'ensayo-redis-'.$sufijo,
            'proveedor' => 'recolector_whatsapp',
            'estado' => 'conectado',
        ]);

        return ChatMonitoreado::query()->create([
            'conexion_id' => $conexion->id,
            'id_chat_proveedor' => 'ensayo-redis-'.$sufijo.'@g.us',
            'tipo_chat' => 'grupo',
            'es_monitoreado' => true,
            'es_activo' => true,
        ]);
    }

    private function crearEvento(ChatMonitoreado $grupo): EventoCrudoWhatsapp
    {
        $clave = 'redis-prueba-'.bin2hex(random_bytes(8));

        $resultado = app(CapturarEventoWhatsappEntrante::class)->ejecutar(new EventoWhatsappEntrante(
            conexionId: (int) $grupo->conexion_id,
            chatId: $grupo->id_chat_proveedor,
            tipoEvento: 'mensaje',
            tipoMensaje: 'texto',
            eventoExternoId: $clave,
            mensajeProveedorId: $clave,
            contenidoCrudo: json_encode(['message' => ['conversation' => 'Prueba sin datos reales']], JSON_THROW_ON_ERROR),
            recibidoEn: CarbonImmutable::now('UTC'),
            versionContrato: 'v3-recolector',
        ));

        $this->assertSame('aceptado', $resultado->estado);

        return EventoCrudoWhatsapp::query()->findOrFail($resultado->eventoCrudoId);
    }
}
