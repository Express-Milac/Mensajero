<?php

namespace Tests\Integracion;

use App\Models\AuditoriaMonitoreoChat;
use App\Models\ChatMonitoreado;
use App\Models\ConexionWhatsapp;
use App\Services\Monitoreo\CambiarMonitoreoChat;
use App\Services\Monitoreo\SincronizarChatsDescubiertos;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdministracionGruposSqlServerTest extends TestCase
{
    public function test_descubrimiento_conserva_autorizacion_y_cambios_quedan_auditados(): void
    {
        $this->assertStringEndsWith('_test', config('database.connections.sqlsrv.database'), 'Use una base desechable cuyo nombre termine en _test.');
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();

        DB::beginTransaction();
        try {
            $conexion = ConexionWhatsapp::create([
                'clave_conexion' => 'grupos-prueba',
                'proveedor' => 'web_autohospedado',
                'estado' => 'conectado',
            ]);

            $sincronizar = app(SincronizarChatsDescubiertos::class);
            $resultado = $sincronizar->ejecutar($conexion, [[
                'id_chat_proveedor' => 'grupo-operacion@g.us',
                'tipo_chat' => 'grupo',
                'nombre_grupo' => 'Operación MILAC',
            ]], CarbonImmutable::parse('2026-10-15T18:00:00Z'));

            $this->assertSame(['creados' => 1, 'actualizados' => 0, 'sin_cambios' => 0], $resultado);

            $chat = ChatMonitoreado::query()->where('id_chat_proveedor', 'grupo-operacion@g.us')->firstOrFail();
            $this->assertFalse($chat->es_monitoreado);
            $this->assertFalse($chat->es_visible_tablero);
            $this->assertSame(0, $chat->version_autorizacion);

            $cambiar = app(CambiarMonitoreoChat::class);
            $chat = $cambiar->ejecutar($chat->id, true, 'consola', null, 'Selección inicial de grupo');
            $auditoriasChat = fn (): int => AuditoriaMonitoreoChat::query()
                ->where('chat_monitoreado_id', $chat->id)
                ->count();

            $this->assertTrue($chat->es_monitoreado);
            $this->assertTrue($chat->es_visible_tablero);
            $this->assertSame(1, $chat->version_autorizacion);
            $this->assertSame(1, $auditoriasChat());
            $this->assertDatabaseHas('mensajero_auditoria_monitoreo_chats', [
                'chat_monitoreado_id' => $chat->id,
                'version_autorizacion' => 1,
                'es_monitoreado_anterior' => false,
                'es_monitoreado_nuevo' => true,
                'actor_tipo' => 'consola',
            ]);

            $sincronizar->ejecutar($conexion, [[
                'id_chat_proveedor' => 'grupo-operacion@g.us',
                'tipo_chat' => 'grupo',
                'nombre_grupo' => 'Operación MILAC actualizado',
            ]], CarbonImmutable::parse('2026-10-15T19:00:00Z'));

            $chat->refresh();
            $this->assertTrue($chat->es_monitoreado, 'El redescubrimiento no debe revocar la autorización.');
            $this->assertSame(1, $chat->version_autorizacion);
            $this->assertSame('Operación MILAC actualizado', $chat->nombre_grupo);

            $sinCambio = $cambiar->ejecutar($chat->id, true, 'consola');
            $this->assertSame(1, $sinCambio->version_autorizacion);
            $this->assertSame(1, $auditoriasChat());

            $chat = $cambiar->ejecutar($chat->id, false, 'consola', null, 'Retiro de prueba');
            $this->assertFalse($chat->es_monitoreado);
            $this->assertFalse($chat->es_visible_tablero);
            $this->assertSame(2, $chat->version_autorizacion);
            $this->assertSame(2, $auditoriasChat());
        } finally {
            DB::rollBack();
        }
    }
}
