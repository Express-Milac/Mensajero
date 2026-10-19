<?php

namespace Tests\Integracion;

use App\Data\WhatsApp\EventoWhatsappEntrante;
use App\Models\ChatMonitoreado;
use App\Models\ConexionWhatsapp;
use App\Models\EventoCrudoWhatsapp;
use App\Models\MensajeWhatsapp;
use App\Services\Capture\CapturarEventoWhatsappEntrante;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NormalizacionLoteSqlServerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('sqlsrv', config('database.default'));
        $this->assertStringEndsWith('_test', (string) config('database.connections.sqlsrv.database'));
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
    }

    public function test_vista_previa_no_escribe_y_aplicacion_exacta_es_idempotente(): void
    {
        DB::beginTransaction();

        try {
            $grupo = $this->crearGrupo();
            $primero = $this->crearEvento($grupo);
            $segundo = $this->crearEvento($grupo);
            $tercero = $this->crearEvento($grupo);

            $codigo = Artisan::call('mensajero:normalizar-lote', [
                'grupo' => $grupo->id,
                '--limite' => 2,
            ]);
            $salida = Artisan::output();

            $this->assertSame(0, $codigo, 'Vista previa fallida: '.$salida);
            $this->assertStringContainsString(
                'IDs: '.$primero->id.','.$segundo->id,
                $salida,
                'La vista previa debe listar exactamente los dos primeros eventos elegibles. Salida: '.$salida,
            );

            $this->assertSame(0, MensajeWhatsapp::query()
                ->whereIn('evento_crudo_id', [$primero->id, $segundo->id, $tercero->id])
                ->count());

            $identificadores = implode(',', [$primero->id, $segundo->id]);
            $argumentos = [
                'grupo' => $grupo->id,
                '--limite' => 2,
                '--ids' => $identificadores,
                '--aplicar' => true,
            ];

            $this->artisan('mensajero:normalizar-lote', $argumentos)->assertSuccessful();

            $this->assertSame(1, MensajeWhatsapp::query()->where('evento_crudo_id', $primero->id)->count());
            $this->assertSame(1, MensajeWhatsapp::query()->where('evento_crudo_id', $segundo->id)->count());
            $this->assertSame(0, MensajeWhatsapp::query()->where('evento_crudo_id', $tercero->id)->count());
            $this->assertSame('capturado', $tercero->fresh()->estado_procesamiento);
            $this->assertSame('normalizado', $primero->fresh()->estado_procesamiento);

            $this->artisan('mensajero:normalizar-lote', $argumentos)->assertSuccessful();

            $this->assertSame(2, MensajeWhatsapp::query()
                ->whereIn('evento_crudo_id', [$primero->id, $segundo->id, $tercero->id])
                ->count());
        } finally {
            DB::rollBack();
        }
    }

    public function test_rechaza_ids_cruzados_y_no_procesa_ninguno_del_lote(): void
    {
        DB::beginTransaction();

        try {
            $grupo = $this->crearGrupo();
            $ajeno = $this->crearGrupo();
            $primero = $this->crearEvento($grupo);
            $segundo = $this->crearEvento($ajeno);

            $this->artisan('mensajero:normalizar-lote', [
                'grupo' => $grupo->id,
                '--ids' => implode(',', [$primero->id, $segundo->id]),
                '--aplicar' => true,
            ])->assertFailed();

            $this->assertSame('capturado', $primero->fresh()->estado_procesamiento);
            $this->assertSame('capturado', $segundo->fresh()->estado_procesamiento);
            $this->assertSame(0, MensajeWhatsapp::query()->whereIn(
                'evento_crudo_id',
                [$primero->id, $segundo->id],
            )->count());
        } finally {
            DB::rollBack();
        }
    }

    public function test_impide_aplicacion_sin_ids_y_valores_duplicados(): void
    {
        DB::beginTransaction();

        try {
            $grupo = $this->crearGrupo();
            $evento = $this->crearEvento($grupo);

            $this->artisan('mensajero:normalizar-lote', [
                'grupo' => $grupo->id,
                '--aplicar' => true,
            ])->assertFailed();

            $this->artisan('mensajero:normalizar-lote', [
                'grupo' => $grupo->id,
                '--ids' => $evento->id.','.$evento->id,
                '--aplicar' => true,
            ])->assertFailed();

            $this->artisan('mensajero:normalizar-lote', [
                'grupo' => $grupo->id,
                '--limite' => 6,
            ])->assertFailed();

            $this->artisan('mensajero:normalizar-lote', [
                'grupo' => $grupo->id,
                '--ids' => '999999999999999999999999999999',
                '--aplicar' => true,
            ])->assertFailed();

            $this->assertSame('capturado', $evento->fresh()->estado_procesamiento);
        } finally {
            DB::rollBack();
        }
    }

    public function test_impide_modificacion_en_base_con_nombre_de_produccion(): void
    {
        $original = config('database.connections.sqlsrv.database');

        try {
            config(['database.connections.sqlsrv.database' => 'milacdb']);

            $this->artisan('mensajero:normalizar-lote', [
                'grupo' => 1,
                '--ids' => '1',
                '--aplicar' => true,
            ])->assertFailed();
        } finally {
            config(['database.connections.sqlsrv.database' => $original]);
        }
    }

    private function crearGrupo(): ChatMonitoreado
    {
        $clave = bin2hex(random_bytes(7));
        $conexion = ConexionWhatsapp::query()->create([
            'clave_conexion' => 'lote-'.$clave,
            'proveedor' => 'recolector_whatsapp',
            'estado' => 'conectado',
        ]);

        return ChatMonitoreado::query()->create([
            'conexion_id' => $conexion->id,
            'id_chat_proveedor' => 'lote-'.$clave.'@g.us',
            'tipo_chat' => 'grupo',
            'es_monitoreado' => true,
            'es_activo' => true,
        ]);
    }

    private function crearEvento(ChatMonitoreado $grupo): EventoCrudoWhatsapp
    {
        $clave = 'lote-'.bin2hex(random_bytes(8));
        $evento = new EventoWhatsappEntrante(
            conexionId: (int) $grupo->conexion_id,
            chatId: $grupo->id_chat_proveedor,
            tipoEvento: 'mensaje',
            tipoMensaje: 'texto',
            eventoExternoId: $clave,
            mensajeProveedorId: $clave,
            contenidoCrudo: json_encode([
                'message' => ['conversation' => 'Texto de prueba sintética'],
            ], JSON_THROW_ON_ERROR),
            recibidoEn: CarbonImmutable::now('UTC'),
            versionContrato: 'v3-recolector',
        );

        $resultado = app(CapturarEventoWhatsappEntrante::class)->ejecutar($evento);
        $this->assertSame('aceptado', $resultado->estado);

        return EventoCrudoWhatsapp::query()->findOrFail($resultado->eventoCrudoId);
    }
}
