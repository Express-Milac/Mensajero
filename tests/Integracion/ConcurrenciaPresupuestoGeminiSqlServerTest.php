<?php

namespace Tests\Integracion;

use App\Models\ChatMonitoreado;
use App\Models\ConexionWhatsapp;
use App\Services\IA\ReservarPresupuestoGemini;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Compite por el MISMO presupuesto con sesiones PDO distintas y grupos
 * diferentes. SOLO SQL Server mensajero_integracion_test; no Google ni Redis.
 */
final class ConcurrenciaPresupuestoGeminiSqlServerTest extends TestCase
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
        config()->set('mensajero.tiempo_real.habilitado', false);
        Bus::fake();
        Http::preventStrayRequests();
        Http::fake();
    }

    public function test_dos_conexiones_de_grupos_distintos_compiten_por_un_solo_cupo_global(): void
    {
        $principal = DB::connection('sqlsrv');
        $secundaria = null;
        $conexiones = [];
        $grupos = [];
        $fuentePrimera = random_int(1_000_000, 2_000_000_000);
        $fuenteSegunda = random_int(1_000_000, 2_000_000_000);

        try {
            // Fuentes sintéticas CONFIRMADAS: la otra sesión puede leerlas.
            foreach (range(1, 2) as $numero) {
                $clave = 'presupuesto-concurrencia-'.bin2hex(random_bytes(10));
                $conexion = ConexionWhatsapp::query()->create([
                    'clave_conexion' => $clave,
                    'proveedor' => 'recolector_whatsapp',
                    'estado' => 'conectado',
                ]);
                $conexiones[] = (int) $conexion->id;
                $grupo = ChatMonitoreado::query()->create([
                    'conexion_id' => (int) $conexion->id,
                    'id_chat_proveedor' => $clave.'@g.us',
                    'tipo_chat' => 'grupo',
                    'es_monitoreado' => true,
                    'es_visible_tablero' => true,
                    'es_activo' => true,
                ]);
                $grupos[] = (int) $grupo->id;
            }

            config()->set('mensajero.ia.modelo', 'gemini-3.5-flash-lite');
            config()->set('mensajero.ia.presupuesto_monetario.habilitado', true);
            config()->set('mensajero.ia.presupuesto_monetario.tarifa_validada', true);
            config()->set('mensajero.ia.presupuesto_monetario.tarifa_referencia', 'tarifa-ficticia-no-operativa');
            config()->set('mensajero.ia.presupuesto_monetario.reserva_maxima_microusd', 2_000_000);
            config()->set('mensajero.ia.presupuesto_monetario.limite_diario_microusd', 3_000_000);
            config()->set('mensajero.ia.presupuesto_monetario.limite_mensual_microusd', 3_000_000);

            config()->set('database.connections.sqlsrv_presupuesto_concurrencia', config('database.connections.sqlsrv'));
            DB::purge('sqlsrv_presupuesto_concurrencia');
            $secundaria = DB::connection('sqlsrv_presupuesto_concurrencia');

            $this->assertSame(
                'mensajero_integracion_test',
                $secundaria->selectOne('SELECT DB_NAME() AS nombre')->nombre,
            );
            $this->assertNotSame($principal->getPdo(), $secundaria->getPdo());

            // Servicio 1 reserva DENTRO de la primera transacción real.
            // Su transacción interna no libera el candado global hasta COMMIT.
            $principal->beginTransaction();
            $libro = app(ReservarPresupuestoGemini::class);
            $this->assertSame('reservada', $libro->reservar('lote', $fuentePrimera, $grupos[0]));
            $this->assertSame(1, $principal->table('mensajero_reservas_consumo_gemini')
                ->where('tipo_fuente', 'lote')->where('fuente_id', $fuentePrimera)->count());

            // Sesión 2 sobre otro grupo: NOWAIT en la FILA GLOBAL debe
            // producir exactamente el error de bloqueo 1222 (no deadlock).
            $bloqueada = false;
            try {
                $secundaria->selectOne(
                    'SELECT id FROM dbo.mensajero_candado_presupuesto_gemini WITH (UPDLOCK, ROWLOCK, NOWAIT) WHERE id = ?',
                    [1],
                );
            } catch (QueryException $error) {
                $codigo = (int) ($error->errorInfo[1] ?? 0);
                $this->assertTrue(
                    $codigo === 1222 || str_contains($error->getMessage(), '1222'),
                    'Se exige el conflicto de bloqueo SQL Server 1222.',
                );
                $bloqueada = true;
            }
            $this->assertTrue($bloqueada, 'El segundo grupo debe quedar bloqueado por el candado global.');

            $principal->commit();

            // Tras liberar la fila global, el segundo grupo observa el
            // importe ya reservado por el PRIMERO y no rebasa el presupuesto.
            $this->assertSame('limite_diario',
                $libro->reservar('mensaje', $fuenteSegunda, $grupos[1]));
            $this->assertSame(0, $principal->table('mensajero_reservas_consumo_gemini')
                ->where('tipo_fuente', 'mensaje')->where('fuente_id', $fuenteSegunda)->count());
            $this->assertSame(1, $principal->table('mensajero_reservas_consumo_gemini')
                ->where('tipo_fuente', 'lote')->where('fuente_id', $fuentePrimera)->count());
            Bus::assertNothingDispatched();
            Http::assertNothingSent();
        } finally {
            if ($secundaria !== null && $secundaria->transactionLevel() > 0) {
                $secundaria->rollBack();
            }
            if ($principal->transactionLevel() > 0) {
                $principal->rollBack();
            }
            // Eliminar exclusivamente datos sintéticos, en orden de FK.
            $principal->table('mensajero_reservas_consumo_gemini')
                ->where('tipo_fuente', 'lote')
                ->where('fuente_id', $fuentePrimera)
                ->whereIn('chat_monitoreado_id', $grupos)->delete();
            $principal->table('mensajero_reservas_consumo_gemini')
                ->where('tipo_fuente', 'mensaje')
                ->where('fuente_id', $fuenteSegunda)
                ->whereIn('chat_monitoreado_id', $grupos)->delete();
            foreach ($grupos as $grupoId) {
                $principal->table('mensajero_chats_monitoreados')->where('id', $grupoId)->delete();
            }
            foreach ($conexiones as $conexionId) {
                $principal->table('mensajero_conexiones_whatsapp')->where('id', $conexionId)->delete();
            }
            if ($secundaria !== null) {
                DB::purge('sqlsrv_presupuesto_concurrencia');
            }
            config()->set('database.connections.sqlsrv_presupuesto_concurrencia', null);
        }
    }
}
