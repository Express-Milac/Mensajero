<?php

namespace Tests\Integracion;

use App\Jobs\AnalizarLoteGeminiTrabajo;
use App\Models\ChatMonitoreado;
use App\Models\ConexionWhatsapp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * Comprueba fallas posteriores al reclamo del lote, exclusivamente en SQL Server aislado.
 * No ejecuta workers, colas Redis ni solicitudes a Gemini.
 */
final class FallosTrabajoGeminiSqlServerTest extends TestCase
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
        Http::fake();
    }

    public function test_falla_de_trabajo_marca_lote_iniciado_para_revision_sin_reintentar(): void
    {
        DB::beginTransaction();
        try {
            $grupo = $this->crearGrupoFicticio();
            $loteId = $this->crearLoteFicticio($grupo, 'procesando');
            $trabajo = new AnalizarLoteGeminiTrabajo($loteId);

            $this->assertSame(1, $trabajo->tries);
            $this->assertSame('mensajero_ia_lotes', $trabajo->queue);

            $trabajo->failed(new RuntimeException('Error ficticio sin peticion externa'));

            $this->assertSame('requiere_revision', DB::table('mensajero_lotes_gemini')
                ->where('id', $loteId)->value('estado'));
            $this->assertSame('RuntimeException', DB::table('mensajero_lotes_gemini')
                ->where('id', $loteId)->value('error_tipo'));

            // Una segunda notificacion de fallo no modifica estados ya resueltos.
            $trabajo->failed(null);
            $this->assertSame('requiere_revision', DB::table('mensajero_lotes_gemini')
                ->where('id', $loteId)->value('estado'));
            $this->assertSame('RuntimeException', DB::table('mensajero_lotes_gemini')
                ->where('id', $loteId)->value('error_tipo'));
            Http::assertNothingSent();
        } finally {
            DB::rollBack();
        }
    }

    public function test_notificacion_de_fallo_no_altera_lotes_reservados_o_finalizados(): void
    {
        DB::beginTransaction();
        try {
            $grupo = $this->crearGrupoFicticio();
            foreach (['reservado', 'finalizado'] as $estado) {
                $loteId = $this->crearLoteFicticio($grupo, $estado);
                (new AnalizarLoteGeminiTrabajo($loteId))->failed(new RuntimeException('Fallo tardio ficticio'));

                $this->assertSame($estado, DB::table('mensajero_lotes_gemini')
                    ->where('id', $loteId)->value('estado'));
                $this->assertNull(DB::table('mensajero_lotes_gemini')
                    ->where('id', $loteId)->value('error_tipo'));
            }
            Http::assertNothingSent();
        } finally {
            DB::rollBack();
        }
    }

    private function crearGrupoFicticio(): ChatMonitoreado
    {
        $clave = bin2hex(random_bytes(12));
        $conexion = ConexionWhatsapp::query()->create([
            'clave_conexion' => 'fallo-lote-'.$clave,
            'proveedor' => 'recolector_whatsapp',
            'estado' => 'conectado',
        ]);

        return ChatMonitoreado::query()->create([
            'conexion_id' => $conexion->id,
            'id_chat_proveedor' => 'fallo-lote-'.$clave.'@g.us',
            'tipo_chat' => 'grupo',
            'es_monitoreado' => true,
            'es_visible_tablero' => true,
            'es_activo' => true,
        ]);
    }

    private function crearLoteFicticio(ChatMonitoreado $grupo, string $estado): int
    {
        return (int) DB::table('mensajero_lotes_gemini')->insertGetId([
            'chat_monitoreado_id' => $grupo->id,
            'hash_mensajes' => hash('sha256', bin2hex(random_bytes(16))),
            'estado' => $estado,
            'reservado_en' => now('UTC')->format('Y-m-d H:i:s.u'),
        ]);
    }
}
