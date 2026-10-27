<?php

namespace Tests\Integracion;

use App\Models\ChatMonitoreado;
use App\Models\ConexionWhatsapp;
use App\Models\EventoCrudoWhatsapp;
use App\Models\MensajeBuzonSalida;
use App\Services\Normalizacion\NormalizarEventoWhatsapp;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ConciliacionPublicacionesSqlServerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('sqlsrv', config('database.default'));
        $this->assertStringEndsWith('_test', (string) config('database.connections.sqlsrv.database'));
        config(['mensajero.normalizacion.habilitada' => false]);
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
    }

    public function test_vista_previa_no_escribe_y_aplicacion_acotada_recupera_aviso_sintetico(): void
    {
        DB::beginTransaction();

        try {
            Queue::fake();

            [$primero, $primeraCola] = $this->crearEnsayo(true);
            [$segundo, $segundaCola] = $this->crearEnsayo(true);
            [$reciente, $terceraCola] = $this->crearEnsayo(false);

            $huellas = [
                $primero->id => $primero->hash_contenido_sha256,
                $segundo->id => $segundo->hash_contenido_sha256,
            ];

            $this->assertSame(0, Artisan::call('mensajero:conciliar-publicaciones', [
                '--limite' => 1,
                '--minutos' => 30,
            ]));

            $this->assertNotNull($primeraCola->fresh()->enviado_en);
            $this->assertNotNull($segundaCola->fresh()->enviado_en);
            $this->assertNull($terceraCola->fresh()->enviado_en);

            $this->assertSame(0, Artisan::call('mensajero:conciliar-publicaciones', [
                '--limite' => 1,
                '--minutos' => 30,
                '--aplicar' => true,
            ]));

            $this->assertNull($primeraCola->fresh()->enviado_en);
            $this->assertNotNull($segundaCola->fresh()->enviado_en);
            $this->assertNull($terceraCola->fresh()->enviado_en);

            $this->assertSame(0, Artisan::call('mensajero:conciliar-publicaciones', [
                '--limite' => 20,
                '--minutos' => 30,
                '--aplicar' => true,
            ]));

            $this->assertNull($segundaCola->fresh()->enviado_en);
            $this->assertNull($terceraCola->fresh()->enviado_en);
            $this->assertSame('capturado', $primero->fresh()->estado_procesamiento);
            $this->assertSame('capturado', $segundo->fresh()->estado_procesamiento);
            $this->assertSame('capturado', $reciente->fresh()->estado_procesamiento);
            $this->assertSame($huellas[$primero->id], $primero->fresh()->hash_contenido_sha256);
            $this->assertSame($huellas[$segundo->id], $segundo->fresh()->hash_contenido_sha256);
            Queue::assertNothingPushed();
        } finally {
            DB::rollBack();
        }
    }

    public function test_no_reabre_grupo_revocado_ni_aviso_de_evento_ya_normalizado(): void
    {
        DB::beginTransaction();

        try {
            Queue::fake();

            [$revocado, $avisoRevocado] = $this->crearEnsayo(true);
            [$normalizado, $avisoNormalizado] = $this->crearEnsayo(true);

            ChatMonitoreado::query()
                ->whereKey($revocado->chat_monitoreado_id)
                ->update(['es_monitoreado' => false]);

            $this->assertSame('normalizado', app(NormalizarEventoWhatsapp::class)->ejecutar((int) $normalizado->id));

            $this->assertSame(0, Artisan::call('mensajero:conciliar-publicaciones', [
                '--limite' => 20,
                '--minutos' => 30,
                '--aplicar' => true,
            ]));

            $this->assertNotNull($avisoRevocado->fresh()->enviado_en);
            $this->assertNotNull($avisoNormalizado->fresh()->enviado_en);
            $this->assertSame('capturado', $revocado->fresh()->estado_procesamiento);
            $this->assertSame('normalizado', $normalizado->fresh()->estado_procesamiento);
            Queue::assertNothingPushed();
        } finally {
            DB::rollBack();
        }
    }

    public function test_rechaza_parametros_fuera_de_rango_y_base_no_test(): void
    {
        $this->assertSame(1, Artisan::call('mensajero:conciliar-publicaciones', [
            '--minutos' => 29,
        ]));
        $this->assertSame(1, Artisan::call('mensajero:conciliar-publicaciones', [
            '--limite' => 21,
        ]));

        $original = config('database.connections.sqlsrv.database');

        try {
            config(['database.connections.sqlsrv.database' => 'milacdb']);

            $this->assertSame(1, Artisan::call('mensajero:conciliar-publicaciones', [
                '--aplicar' => true,
            ]));

            config([
                'database.connections.sqlsrv.database' => $original,
                'mensajero.normalizacion.habilitada' => true,
            ]);

            $this->assertSame(1, Artisan::call('mensajero:conciliar-publicaciones', [
                '--aplicar' => true,
            ]));
        } finally {
            config([
                'database.connections.sqlsrv.database' => $original,
                'mensajero.normalizacion.habilitada' => false,
            ]);
        }
    }

    /**
     * @return array{0: EventoCrudoWhatsapp, 1: MensajeBuzonSalida}
     */
    private function crearEnsayo(bool $simularPerdida): array
    {
        $opciones = ['--aplicar' => true];

        if ($simularPerdida) {
            $opciones['--simular-perdida'] = true;
        }

        $this->assertSame(0, Artisan::call('mensajero:preparar-ensayo-redis', $opciones));

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

        return [$evento, $aviso];
    }
}
