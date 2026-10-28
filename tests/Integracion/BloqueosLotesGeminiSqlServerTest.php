<?php

namespace Tests\Integracion;

use App\Models\ChatMonitoreado;
use App\Models\ConexionWhatsapp;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Prueba dos conexiones SQL Server independientes: no ejecuta Gemini ni Redis.
 * Nunca utilizarla fuera de la base exclusiva mensajero_integracion_test.
 */
final class BloqueosLotesGeminiSqlServerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->assertTrue(app()->environment('testing'));
        $this->assertSame('sqlsrv', config('database.default'));
        $this->assertSame('mensajero_integracion_test', config('database.connections.sqlsrv.database'));
        $this->assertSame('mensajero_integracion_test', DB::selectOne('SELECT DB_NAME() AS nombre')->nombre);
        $this->assertTrue(Schema::hasTable('mensajero_lotes_gemini'));

        config()->set('mensajero.ia.lotes_habilitados', false);
        Http::preventStrayRequests();
    }

    public function test_dos_conexiones_no_reclaman_el_mismo_lote_mientras_el_grupo_esta_bloqueado(): void
    {
        $principal = DB::connection('sqlsrv');
        $conexionId = null;
        $grupoId = null;
        $loteId = null;
        $secundaria = null;

        try {
            // Las filas sintéticas deben estar confirmadas para que ambas sesiones puedan verlas.
            $clave = bin2hex(random_bytes(12));
            $conexion = ConexionWhatsapp::query()->create([
                'clave_conexion' => 'sql-bloqueo-'.$clave,
                'proveedor' => 'recolector_whatsapp',
                'estado' => 'conectado',
            ]);
            $conexionId = (int) $conexion->id;

            $grupo = ChatMonitoreado::query()->create([
                'conexion_id' => $conexionId,
                'id_chat_proveedor' => 'sql-bloqueo-'.$clave.'@g.us',
                'tipo_chat' => 'grupo',
                'es_monitoreado' => true,
                'es_visible_tablero' => true,
                'es_activo' => true,
            ]);
            $grupoId = (int) $grupo->id;

            $loteId = (int) $principal->table('mensajero_lotes_gemini')->insertGetId([
                'chat_monitoreado_id' => $grupoId,
                'hash_mensajes' => hash('sha256', $clave),
                'estado' => 'publicado',
                'reservado_en' => now('UTC')->format('Y-m-d H:i:s.u'),
            ]);

            // Cada conexión usa una sesión PDO física distinta del mismo servidor.
            config(['database.connections.sqlsrv_concurrencia' => config('database.connections.sqlsrv')]);
            DB::purge('sqlsrv_concurrencia');
            $secundaria = DB::connection('sqlsrv_concurrencia');
            $this->assertSame(
                'mensajero_integracion_test',
                $secundaria->selectOne('SELECT DB_NAME() AS nombre')->nombre
            );
            $this->assertNotSame($principal->getPdo(), $secundaria->getPdo());
            $secundaria->statement('SET LOCK_TIMEOUT 700');

            // Consumidor 1: mantiene el bloqueo del grupo hasta confirmar el reclamo.
            $principal->beginTransaction();
            $primerGrupo = $principal->table('mensajero_chats_monitoreados')
                ->where('id', $grupoId)->lockForUpdate()->first(['id']);
            $this->assertNotNull($primerGrupo);

            $primerLote = $principal->table('mensajero_lotes_gemini')
                ->where('id', $loteId)->lockForUpdate()->first(['estado']);
            $this->assertSame('publicado', $primerLote->estado);
            $this->assertSame(1, $principal->table('mensajero_lotes_gemini')
                ->where('id', $loteId)->where('estado', 'publicado')
                ->update(['estado' => 'procesando']));

            // Consumidor 2: no debe atravesar un bloqueo vigente del grupo.
            $rechazadaPorBloqueo = false;
            try {
                $secundaria->table('mensajero_chats_monitoreados')
                    ->where('id', $grupoId)->lockForUpdate()->first(['id']);
            } catch (QueryException $error) {
                $this->assertSame(
                    1222,
                    (int) ($error->errorInfo[1] ?? 0),
                    'Se esperaba exclusivamente el timeout de bloqueo de SQL Server (1222).'
                );
                $rechazadaPorBloqueo = true;
            }
            $this->assertTrue($rechazadaPorBloqueo, 'Una segunda sesión no debe cruzar el bloqueo del grupo.');

            $principal->commit();

            // Consumidor 2 vuelve a entrar, pero observa que ya fue reclamado.
            $secundaria->beginTransaction();
            $segundoGrupo = $secundaria->table('mensajero_chats_monitoreados')
                ->where('id', $grupoId)->lockForUpdate()->first(['id']);
            $this->assertNotNull($segundoGrupo);

            $segundoLote = $secundaria->table('mensajero_lotes_gemini')
                ->where('id', $loteId)->lockForUpdate()->first(['estado']);
            $this->assertSame('procesando', $segundoLote->estado);
            $this->assertSame(0, $secundaria->table('mensajero_lotes_gemini')
                ->where('id', $loteId)->where('estado', 'publicado')
                ->update(['estado' => 'procesando']));
            $secundaria->commit();

            $this->assertSame('procesando', $principal->table('mensajero_lotes_gemini')
                ->where('id', $loteId)->value('estado'));
            Http::assertNothingSent();
        } finally {
            // Liberar bloqueos antes de retirar exclusivamente las filas sintéticas.
            if ($secundaria !== null && $secundaria->transactionLevel() > 0) {
                $secundaria->rollBack();
            }
            if ($principal->transactionLevel() > 0) {
                $principal->rollBack();
            }
            if ($loteId !== null) {
                $principal->table('mensajero_lotes_gemini')->where('id', $loteId)->delete();
            }
            if ($grupoId !== null) {
                $principal->table('mensajero_chats_monitoreados')->where('id', $grupoId)->delete();
            }
            if ($conexionId !== null) {
                $principal->table('mensajero_conexiones_whatsapp')->where('id', $conexionId)->delete();
            }
            if ($secundaria !== null) {
                DB::purge('sqlsrv_concurrencia');
            }
            config(['database.connections.sqlsrv_concurrencia' => null]);
        }
    }
}
