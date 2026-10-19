<?php

namespace Tests\Integracion;

use App\Data\WhatsApp\EventoWhatsappEntrante;
use App\Models\ChatMonitoreado;
use App\Models\ConexionWhatsapp;
use App\Models\EventoCrudoWhatsapp;
use App\Models\MensajeWhatsapp;
use App\Services\Capture\CapturarEventoWhatsappEntrante;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NormalizacionControladaSqlServerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('sqlsrv', config('database.default'));
        $this->assertStringEndsWith('_test', (string) config('database.connections.sqlsrv.database'));
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
    }

    public function test_previsualiza_sin_escrituras_y_aplica_solo_el_evento_elegido_de_forma_idempotente(): void
    {
        DB::beginTransaction();

        try {
            [$grupo, $evento] = $this->crearEvento();
            $otro = $this->crearEventoEnGrupo($grupo, 'mensaje-adicional-'.bin2hex(random_bytes(4)));

            $this->artisan('mensajero:normalizar-evento', ['evento' => $evento->id])
                ->assertSuccessful();

            $this->assertSame('capturado', $evento->fresh()->estado_procesamiento);
            $this->assertSame(0, MensajeWhatsapp::query()->where('evento_crudo_id', $evento->id)->count());

            $this->artisan('mensajero:normalizar-evento', [
                'evento' => $evento->id,
                '--aplicar' => true,
            ])->assertSuccessful();

            $this->assertSame('normalizado', $evento->fresh()->estado_procesamiento);
            $this->assertSame(1, MensajeWhatsapp::query()->where('evento_crudo_id', $evento->id)->count());
            $this->assertSame('capturado', $otro->fresh()->estado_procesamiento);
            $this->assertSame(0, MensajeWhatsapp::query()->where('evento_crudo_id', $otro->id)->count());

            $this->artisan('mensajero:normalizar-evento', [
                'evento' => $evento->id,
                '--aplicar' => true,
            ])->assertSuccessful();

            $this->assertSame(1, MensajeWhatsapp::query()->where('evento_crudo_id', $evento->id)->count());
        } finally {
            DB::rollBack();
        }
    }

    public function test_impide_modificar_mensajes_de_grupos_no_autorizados(): void
    {
        DB::beginTransaction();

        try {
            [$grupo, $evento] = $this->crearEvento();
            $grupo->update(['es_monitoreado' => false]);

            $this->artisan('mensajero:normalizar-evento', [
                'evento' => $evento->id,
                '--aplicar' => true,
            ])->assertFailed();

            $this->assertSame('capturado', $evento->fresh()->estado_procesamiento);
        } finally {
            DB::rollBack();
        }
    }

    public function test_bloquea_base_sin_sufijo_test_antes_de_acceder_a_un_evento(): void
    {
        $original = config('database.connections.sqlsrv.database');

        try {
            config(['database.connections.sqlsrv.database' => 'milacdb']);

            $this->artisan('mensajero:normalizar-evento', [
                'evento' => 1,
                '--aplicar' => true,
            ])->assertFailed();
        } finally {
            config(['database.connections.sqlsrv.database' => $original]);
        }
    }

    /**
     * @return array{0: ChatMonitoreado, 1: EventoCrudoWhatsapp}
     */
    private function crearEvento(): array
    {
        $sufijo = bin2hex(random_bytes(6));
        $conexion = ConexionWhatsapp::query()->create([
            'clave_conexion' => 'ensayo-'.$sufijo,
            'proveedor' => 'recolector_whatsapp',
            'estado' => 'conectado',
        ]);

        $grupo = ChatMonitoreado::query()->create([
            'conexion_id' => $conexion->id,
            'id_chat_proveedor' => 'ensayo-'.$sufijo.'@g.us',
            'nombre_grupo' => 'Grupo del ensayo',
            'tipo_chat' => 'grupo',
            'es_monitoreado' => true,
            'es_activo' => true,
        ]);

        return [$grupo, $this->crearEventoEnGrupo($grupo, 'ensayo-'.$sufijo)];
    }

    private function crearEventoEnGrupo(ChatMonitoreado $grupo, string $clave): EventoCrudoWhatsapp
    {
        $resultado = app(CapturarEventoWhatsappEntrante::class)->ejecutar(new EventoWhatsappEntrante(
            conexionId: (int) $grupo->conexion_id,
            chatId: $grupo->id_chat_proveedor,
            tipoEvento: 'mensaje',
            tipoMensaje: 'texto',
            eventoExternoId: $clave,
            mensajeProveedorId: $clave,
            contenidoCrudo: json_encode(['message' => ['conversation' => 'Prueba controlada']], JSON_THROW_ON_ERROR),
            recibidoEn: CarbonImmutable::now('UTC'),
            versionContrato: 'v3-recolector',
        ));

        $this->assertSame('aceptado', $resultado->estado);

        return EventoCrudoWhatsapp::query()->findOrFail($resultado->eventoCrudoId);
    }
}
