<?php

namespace Tests\Integracion;

use App\Models\ChatMonitoreado;
use App\Models\ConexionWhatsapp;
use App\Services\IA\ReservarPresupuestoGemini;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Exclusivamente SQL Server test, sin Google, colas ni precios reales.
 * Valida el libro contable independiente, NO autoriza envío a Gemini.
 */
final class ReservarPresupuestoGeminiSqlServerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->assertTrue(app()->environment('testing'));
        $this->assertSame('sqlsrv', config('database.default'));
        $this->assertSame('mensajero_integracion_test', config('database.connections.sqlsrv.database'));
        $this->assertSame('mensajero_integracion_test', DB::selectOne('SELECT DB_NAME() AS nombre')->nombre);
        $this->assertTrue(Schema::hasTable('mensajero_candado_presupuesto_gemini'));
        $this->assertTrue(Schema::hasTable('mensajero_reservas_consumo_gemini'));

        config()->set('mensajero.ia.habilitada', false);
        config()->set('mensajero.ia.envio_autorizado', false);
        config()->set('mensajero.ia.lotes_habilitados', false);
        config()->set('mensajero.ia.presupuesto_monetario.habilitado', false);

        Bus::fake();
        Http::preventStrayRequests();
        Http::fake();
    }

    public function test_presupuesto_apagado_falla_cerrado_y_no_reserva(): void
    {
        DB::beginTransaction();
        try {
            $grupo = $this->crearGrupo();
            $this->assertSame('deshabilitado',
                app(ReservarPresupuestoGemini::class)->reservar('lote', 101, $grupo->id));
            $this->assertSame(0, DB::table('mensajero_reservas_consumo_gemini')->count());
            Bus::assertNothingDispatched();
            Http::assertNothingSent();
        } finally {
            DB::rollBack();
        }
    }

    public function test_idempotencia_y_candado_global_comparten_limite_entre_grupos(): void
    {
        DB::beginTransaction();
        try {
            $primero = $this->crearGrupo();
            $segundo = $this->crearGrupo();
            $this->habilitarSoloLibro(2_000_000, 3_000_000, 9_000_000);

            $libro = app(ReservarPresupuestoGemini::class);
            $this->assertSame('reservada', $libro->reservar('lote', 201, $primero->id));
            $this->assertSame('ya_reservado', $libro->reservar('lote', 201, $primero->id));
            $this->assertSame('limite_diario', $libro->reservar('mensaje', 202, $segundo->id));
            $this->assertSame(1, DB::table('mensajero_reservas_consumo_gemini')->count());
            $this->assertSame(2_000_000, (int) DB::table('mensajero_reservas_consumo_gemini')
                ->sum('importe_reservado_microusd'));
            $this->assertSame(1, DB::table('mensajero_candado_presupuesto_gemini')
                ->where('id', 1)->count());
            Bus::assertNothingDispatched();
            Http::assertNothingSent();
        } finally {
            DB::rollBack();
        }
    }

    public function test_limite_mensual_bloquea_aun_con_cupo_diario(): void
    {
        DB::beginTransaction();
        try {
            $grupo = $this->crearGrupo();
            $this->habilitarSoloLibro(2_000_000, 8_000_000, 3_000_000);

            $libro = app(ReservarPresupuestoGemini::class);
            $this->assertSame('reservada', $libro->reservar('lote', 301, $grupo->id));
            $this->assertSame('limite_mensual', $libro->reservar('lote', 302, $grupo->id));
            $this->assertSame(1, DB::table('mensajero_reservas_consumo_gemini')->count());
            Bus::assertNothingDispatched();
            Http::assertNothingSent();
        } finally {
            DB::rollBack();
        }
    }

    public function test_reserva_incierta_y_finalizada_retienen_su_importe(): void
    {
        DB::beginTransaction();
        try {
            $grupo = $this->crearGrupo();
            $this->habilitarSoloLibro(2_000_000, 3_000_000, 3_000_000);

            $libro = app(ReservarPresupuestoGemini::class);
            $this->assertSame('reservada', $libro->reservar('lote', 401, $grupo->id));
            $this->assertTrue($libro->marcarIncierta('lote', 401));
            $this->assertSame('incierta', DB::table('mensajero_reservas_consumo_gemini')
                ->where('tipo_fuente', 'lote')->where('fuente_id', 401)->value('estado'));
            $this->assertSame('limite_diario', $libro->reservar('lote', 402, $grupo->id));

            // Tampoco se restituye cupo por marcar el trabajo terminado.
            DB::table('mensajero_reservas_consumo_gemini')->where('fuente_id', 401)
                ->update(['estado' => 'finalizada']);
            $this->assertSame('limite_diario', $libro->reservar('mensaje', 403, $grupo->id));
            $this->assertFalse($libro->marcarIncierta('lote', 401));
            $this->assertSame(1, DB::table('mensajero_reservas_consumo_gemini')->count());
            Bus::assertNothingDispatched();
            Http::assertNothingSent();
        } finally {
            DB::rollBack();
        }
    }

    public function test_configuracion_incompleta_modelo_erroneo_y_fuentes_invalidas_bloquean(): void
    {
        DB::beginTransaction();
        try {
            $grupo = $this->crearGrupo();
            $this->habilitarSoloLibro(2_000_000, 4_000_000, 4_000_000);
            $libro = app(ReservarPresupuestoGemini::class);

            config()->set('mensajero.ia.presupuesto_monetario.tarifa_validada', false);
            $this->assertSame('deshabilitado', $libro->reservar('lote', 501, $grupo->id));
            config()->set('mensajero.ia.presupuesto_monetario.tarifa_validada', true);

            config()->set('mensajero.ia.modelo', 'modelo-desconocido');
            $this->assertSame('deshabilitado', $libro->reservar('lote', 502, $grupo->id));
            config()->set('mensajero.ia.modelo', 'gemini-3.5-flash-lite');

            config()->set('mensajero.ia.presupuesto_monetario.limite_diario_microusd', 0);
            $this->assertSame('deshabilitado', $libro->reservar('lote', 503, $grupo->id));
            config()->set('mensajero.ia.presupuesto_monetario.limite_diario_microusd', 4_000_000);

            $this->assertSame('datos_invalidos', $libro->reservar('otro', 504, $grupo->id));
            $this->assertSame('datos_invalidos', $libro->reservar('lote', 0, $grupo->id));
            $this->assertSame('datos_invalidos', $libro->reservar('lote', 505, -1));
            $this->assertSame(0, DB::table('mensajero_reservas_consumo_gemini')->count());
            Bus::assertNothingDispatched();
            Http::assertNothingSent();
        } finally {
            DB::rollBack();
        }
    }

    public function test_reservas_de_mes_anterior_no_consuman_cupo_del_mes_actual(): void
    {
        DB::beginTransaction();
        try {
            $grupo = $this->crearGrupo();
            $this->habilitarSoloLibro(2_000_000, 3_000_000, 3_000_000);

            $anterior = now('UTC')->startOfMonth()->subSecond()->format('Y-m-d H:i:s.u');
            DB::table('mensajero_reservas_consumo_gemini')->insert([
                'chat_monitoreado_id' => $grupo->id,
                'tipo_fuente' => 'lote',
                'fuente_id' => 601,
                'modelo' => 'gemini-3.5-flash-lite',
                'tarifa_referencia' => 'tarifa-falsa-para-prueba',
                'importe_reservado_microusd' => 2_000_000,
                'estado' => 'incierta',
                'reservado_en' => $anterior,
                'actualizado_en' => $anterior,
            ]);

            $this->assertSame('reservada', app(ReservarPresupuestoGemini::class)
                ->reservar('mensaje', 602, $grupo->id));
            $this->assertSame(2, DB::table('mensajero_reservas_consumo_gemini')->count());
            Bus::assertNothingDispatched();
            Http::assertNothingSent();
        } finally {
            DB::rollBack();
        }
    }

    private function habilitarSoloLibro(int $importe, int $diario, int $mensual): void
    {
        // Estos números son FICTICIOS: no representan precios reales de Google.
        config()->set('mensajero.ia.modelo', 'gemini-3.5-flash-lite');
        config()->set('mensajero.ia.presupuesto_monetario.habilitado', true);
        config()->set('mensajero.ia.presupuesto_monetario.tarifa_validada', true);
        config()->set('mensajero.ia.presupuesto_monetario.tarifa_referencia', 'tarifa-falsa-para-prueba');
        config()->set('mensajero.ia.presupuesto_monetario.reserva_maxima_microusd', $importe);
        config()->set('mensajero.ia.presupuesto_monetario.limite_diario_microusd', $diario);
        config()->set('mensajero.ia.presupuesto_monetario.limite_mensual_microusd', $mensual);
    }

    private function crearGrupo(): ChatMonitoreado
    {
        $sufijo = bin2hex(random_bytes(10));
        $conexion = ConexionWhatsapp::query()->create([
            'clave_conexion' => 'presupuesto-'.$sufijo,
            'proveedor' => 'recolector_whatsapp',
            'estado' => 'conectado',
        ]);

        return ChatMonitoreado::query()->create([
            'conexion_id' => $conexion->id,
            'id_chat_proveedor' => 'presupuesto-'.$sufijo.'@g.us',
            'tipo_chat' => 'grupo',
            'es_monitoreado' => true,
            'es_visible_tablero' => true,
            'es_activo' => true,
        ]);
    }
}
