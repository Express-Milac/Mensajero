<?php

namespace Tests\Integracion;

use App\Models\ChatMonitoreado;
use App\Models\ConexionWhatsapp;
use App\Models\EventoCrudoWhatsapp;
use App\Models\MensajeBuzonSalida;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PrepararEnsayoRedisSqlServerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('sqlsrv', config('database.default'));
        $this->assertStringEndsWith('_test', (string) config('database.connections.sqlsrv.database'));
        config(['mensajero.normalizacion.habilitada' => false]);
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
    }

    public function test_previsualizacion_no_crea_grupos_mensajes_ni_publica_cola(): void
    {
        $antes = ConexionWhatsapp::query()->where('clave_conexion', 'like', 'ensayo-redis-%')->count();
        Queue::fake();

        $this->assertSame(0, Artisan::call('mensajero:preparar-ensayo-redis'));

        $this->assertSame($antes, ConexionWhatsapp::query()
            ->where('clave_conexion', 'like', 'ensayo-redis-%')->count());
        Queue::assertNothingPushed();
    }

    public function test_crea_una_sola_captura_ficticia_y_aviso_sql_sin_publicar_redis(): void
    {
        DB::beginTransaction();

        try {
            Queue::fake();

            $this->assertSame(0, Artisan::call('mensajero:preparar-ensayo-redis', [
                '--aplicar' => true,
            ]));

            $conexion = ConexionWhatsapp::query()
                ->where('clave_conexion', 'like', 'ensayo-redis-%')
                ->orderByDesc('id')
                ->firstOrFail();

            $grupo = ChatMonitoreado::query()
                ->where('conexion_id', $conexion->id)
                ->sole();

            $evento = EventoCrudoWhatsapp::query()
                ->where('chat_monitoreado_id', $grupo->id)
                ->sole();

            $aviso = MensajeBuzonSalida::query()
                ->where('entidad_id', $evento->id)
                ->where('tema', 'whatsapp.crudo.aceptado')
                ->sole();

            $this->assertSame('grupo', $grupo->tipo_chat);
            $this->assertTrue($grupo->es_activo);
            $this->assertTrue($grupo->es_monitoreado);
            $this->assertSame('capturado', $evento->estado_procesamiento);
            $this->assertSame('v3-recolector', $evento->version_contrato);
            $this->assertSame(hash('sha256', $evento->contenido), $evento->hash_contenido_sha256);
            $this->assertStringContainsString('ENSAYO REDIS MILAC', $evento->contenido);
            $this->assertNull($aviso->enviado_en);
            Queue::assertNothingPushed();
        } finally {
            DB::rollBack();
        }
    }

    public function test_simula_aviso_perdido_sin_redis_y_permite_recuperarlo_sin_modificar_evidencia(): void
    {
        DB::beginTransaction();

        try {
            Queue::fake();
            $antes = ConexionWhatsapp::query()->where('clave_conexion', 'like', 'ensayo-redis-%')->count();

            $this->assertSame(0, Artisan::call('mensajero:preparar-ensayo-redis', [
                '--simular-perdida' => true,
            ]));

            $this->assertSame($antes, ConexionWhatsapp::query()
                ->where('clave_conexion', 'like', 'ensayo-redis-%')->count());

            $this->assertSame(0, Artisan::call('mensajero:preparar-ensayo-redis', [
                '--simular-perdida' => true,
                '--aplicar' => true,
            ]));

            $conexion = ConexionWhatsapp::query()
                ->where('clave_conexion', 'like', 'ensayo-redis-%')
                ->orderByDesc('id')
                ->firstOrFail();

            $evento = EventoCrudoWhatsapp::query()
                ->where('conexion_id', $conexion->id)
                ->sole();

            $aviso = MensajeBuzonSalida::query()
                ->where('tema', 'whatsapp.crudo.aceptado')
                ->where('entidad_id', $evento->id)
                ->sole();

            $huella = $evento->hash_contenido_sha256;
            $fechaSimulada = $aviso->enviado_en;

            $this->assertSame('capturado', $evento->estado_procesamiento);
            $this->assertNotNull($fechaSimulada);
            $this->assertTrue($fechaSimulada->lessThan(now('UTC')->subMinutes(30)));
            $this->assertSame(1, (int) $aviso->intentos);
            Queue::assertNothingPushed();

            // La vista previa comprueba, pero no reabre el aviso.
            $this->assertSame(0, Artisan::call('mensajero:recuperar-publicacion', [
                'evento' => $evento->id,
                '--minutos' => 30,
            ]));
            $this->assertSame($fechaSimulada->format('Y-m-d H:i:s.u'), $aviso->fresh()->enviado_en->format('Y-m-d H:i:s.u'));

            $this->assertSame(0, Artisan::call('mensajero:recuperar-publicacion', [
                'evento' => $evento->id,
                '--minutos' => 30,
                '--aplicar' => true,
            ]));

            $this->assertNull($aviso->fresh()->enviado_en);
            $this->assertSame($huella, $evento->fresh()->hash_contenido_sha256);
            $this->assertSame('capturado', $evento->fresh()->estado_procesamiento);
            Queue::assertNothingPushed();
        } finally {
            DB::rollBack();
        }
    }

    public function test_no_prepara_en_produccion_ni_con_modo_automatico_habilitado(): void
    {
        $anterior = config('database.connections.sqlsrv.database');

        try {
            config(['database.connections.sqlsrv.database' => 'milacdb']);

            $this->assertSame(1, Artisan::call('mensajero:preparar-ensayo-redis', [
                '--aplicar' => true,
            ]));

            config([
                'database.connections.sqlsrv.database' => $anterior,
                'mensajero.normalizacion.habilitada' => true,
            ]);

            $this->assertSame(1, Artisan::call('mensajero:preparar-ensayo-redis', [
                '--aplicar' => true,
            ]));
        } finally {
            config([
                'database.connections.sqlsrv.database' => $anterior,
                'mensajero.normalizacion.habilitada' => false,
            ]);
        }
    }
}
