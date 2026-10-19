<?php

namespace Tests\Integracion;

use App\Models\ChatMonitoreado;
use App\Models\ConexionWhatsapp;
use App\Models\EventoCrudoWhatsapp;
use App\Models\MensajeBuzonSalida;
use App\Services\Normalizacion\MedirEstadoBuzonWhatsapp;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class VigilanciaBuzonSqlServerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('sqlsrv', config('database.default'));
        $this->assertStringEndsWith('_test', (string) config('database.connections.sqlsrv.database'));
        config(['mensajero.normalizacion.habilitada' => false]);
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
    }

    public function test_cuenta_pendientes_atrasos_y_publicaciones_sin_modificar_la_evidencia(): void
    {
        DB::beginTransaction();

        try {
            Queue::fake();
            [$grupo, $evento, $aviso] = $this->crearEnsayo();
            $medidor = app(MedirEstadoBuzonWhatsapp::class);

            $this->assertSame([
                'pendientes_publicacion' => 1,
                'pendientes_atrasados' => 0,
                'publicados_sin_normalizar' => 0,
                'publicados_antiguos' => 0,
                'reservas_vencidas' => 0,
            ], $medidor->ejecutar(30, (int) $grupo->id));

            $huella = $evento->hash_contenido_sha256;
            $aviso->creado_en = now('UTC')->subMinutes(40);
            $aviso->reservado_hasta = now('UTC')->subMinutes(5);
            $aviso->save();

            $this->assertSame([
                'pendientes_publicacion' => 1,
                'pendientes_atrasados' => 1,
                'publicados_sin_normalizar' => 0,
                'publicados_antiguos' => 0,
                'reservas_vencidas' => 1,
            ], $medidor->ejecutar(30, (int) $grupo->id));

            // El código de error sirve para un futuro supervisor, no aplica cambios.
            $this->assertSame(1, Artisan::call('mensajero:vigilar-buzon', [
                '--grupo' => $grupo->id,
                '--minutos' => 30,
                '--alertar' => true,
            ]));
            $this->assertSame(0, Artisan::call('mensajero:vigilar-buzon', [
                '--grupo' => $grupo->id,
                '--minutos' => 30,
            ]));

            $aviso->enviado_en = now('UTC')->subMinutes(40);
            $aviso->reservado_hasta = null;
            $aviso->save();

            $this->assertSame([
                'pendientes_publicacion' => 0,
                'pendientes_atrasados' => 0,
                'publicados_sin_normalizar' => 1,
                'publicados_antiguos' => 1,
                'reservas_vencidas' => 0,
            ], $medidor->ejecutar(30, (int) $grupo->id));

            $this->assertSame($huella, $evento->fresh()->hash_contenido_sha256);
            $this->assertSame('capturado', $evento->fresh()->estado_procesamiento);
            Queue::assertNothingPushed();
        } finally {
            DB::rollBack();
        }
    }

    public function test_grupo_revocado_no_aparece_en_metricas_y_no_se_puede_consultar_por_id(): void
    {
        DB::beginTransaction();

        try {
            [$grupo, $evento, $aviso] = $this->crearEnsayo();
            $aviso->enviado_en = now('UTC')->subMinutes(50);
            $aviso->save();
            $grupo->update(['es_monitoreado' => false]);

            $medidas = app(MedirEstadoBuzonWhatsapp::class)->ejecutar(30, (int) $grupo->id);

            $this->assertSame(0, $medidas['publicados_sin_normalizar']);
            $this->assertSame(0, $medidas['publicados_antiguos']);
            $this->assertSame(1, Artisan::call('mensajero:vigilar-buzon', [
                '--grupo' => $grupo->id,
            ]));
            $this->assertNotNull($aviso->fresh()->enviado_en);
            $this->assertSame('capturado', $evento->fresh()->estado_procesamiento);
        } finally {
            DB::rollBack();
        }
    }

    public function test_desglose_identifica_grupo_y_antiguedad_sin_alterar_aviso(): void
    {
        DB::beginTransaction();

        try {
            Queue::fake();
            [$grupoAntiguo, $eventoAntiguo, $avisoAntiguo] = $this->crearEnsayo();
            [$grupoReciente, $eventoReciente, $avisoReciente] = $this->crearEnsayo();

            $avisoAntiguo->creado_en = now('UTC')->subDays(8);
            $avisoAntiguo->save();
            $avisoReciente->creado_en = now('UTC')->subHours(2);
            $avisoReciente->save();

            $medidor = app(MedirEstadoBuzonWhatsapp::class);
            $antiguo = $medidor->desglosarPendientes(30, (int) $grupoAntiguo->id);
            $reciente = $medidor->desglosarPendientes(30, (int) $grupoReciente->id);

            $this->assertFalse($antiguo['truncado']);
            $this->assertCount(1, $antiguo['grupos']);
            $this->assertSame((int) $grupoAntiguo->id, $antiguo['grupos'][0]['grupo_id']);
            $this->assertSame(1, $antiguo['grupos'][0]['pendientes']);
            $this->assertSame(1, $antiguo['grupos'][0]['atrasados']);
            $this->assertSame(1, $antiguo['grupos'][0]['mayores_24h']);
            $this->assertSame(1, $antiguo['grupos'][0]['mayores_7d']);
            $this->assertTrue($antiguo['grupos'][0]['prefijo_ensayo']);

            $this->assertCount(1, $reciente['grupos']);
            $this->assertSame(1, $reciente['grupos'][0]['pendientes']);
            $this->assertSame(1, $reciente['grupos'][0]['atrasados']);
            $this->assertSame(0, $reciente['grupos'][0]['mayores_24h']);
            $this->assertSame(0, $reciente['grupos'][0]['mayores_7d']);

            $this->assertSame(0, Artisan::call('mensajero:vigilar-buzon', [
                '--grupo' => $grupoAntiguo->id,
                '--minutos' => 30,
                '--desglose' => true,
            ]));

            $this->assertNull($avisoAntiguo->fresh()->enviado_en);
            $this->assertNull($avisoReciente->fresh()->enviado_en);
            $this->assertSame('capturado', $eventoAntiguo->fresh()->estado_procesamiento);
            $this->assertSame('capturado', $eventoReciente->fresh()->estado_procesamiento);
            Queue::assertNothingPushed();
        } finally {
            DB::rollBack();
        }
    }

    public function test_parametros_incorrectos_y_produccion_fallan_antes_de_leer_metricas(): void
    {
        $this->assertSame(1, Artisan::call('mensajero:vigilar-buzon', ['--minutos' => 4]));
        $this->assertSame(1, Artisan::call('mensajero:vigilar-buzon', ['--minutos' => 1441]));
        $this->assertSame(1, Artisan::call('mensajero:vigilar-buzon', ['--grupo' => -1]));

        $nombre = config('database.connections.sqlsrv.database');

        try {
            config(['database.connections.sqlsrv.database' => 'milacdb']);

            $this->assertSame(1, Artisan::call('mensajero:vigilar-buzon'));
        } finally {
            config(['database.connections.sqlsrv.database' => $nombre]);
        }
    }

    /**
     * @return array{0: ChatMonitoreado, 1: EventoCrudoWhatsapp, 2: MensajeBuzonSalida}
     */
    private function crearEnsayo(): array
    {
        $this->assertSame(0, Artisan::call('mensajero:preparar-ensayo-redis', [
            '--aplicar' => true,
        ]));

        $conexion = ConexionWhatsapp::query()
            ->where('clave_conexion', 'like', 'ensayo-redis-%')
            ->orderByDesc('id')
            ->firstOrFail();

        $grupo = ChatMonitoreado::query()->where('conexion_id', $conexion->id)->sole();
        $evento = EventoCrudoWhatsapp::query()->where('conexion_id', $conexion->id)->sole();

        $aviso = MensajeBuzonSalida::query()
            ->where('tema', 'whatsapp.crudo.aceptado')
            ->where('entidad_id', $evento->id)
            ->sole();

        return [$grupo, $evento, $aviso];
    }
}
