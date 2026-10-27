<?php

namespace Tests\Integracion;

use App\Models\ChatMonitoreado;
use App\Models\ConexionWhatsapp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EsquemaCapturaSqlServerTest extends TestCase
{
    public function test_tablas_captura_estan_aisladas_y_lista_permitida_niega_por_defecto(): void
    {
        $this->assertSame('sqlsrv', config('database.default'));
        $this->assertStringEndsWith('_test', config('database.connections.sqlsrv.database'), 'Use una base desechable cuyo nombre termine en _test.');
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();

        foreach (['mensajero_conexiones_whatsapp', 'mensajero_chats_monitoreados', 'mensajero_contactos_whatsapp', 'mensajero_eventos_crudos_whatsapp', 'mensajero_buzon_salida', 'mensajero_mensajes_whatsapp'] as $tabla) {
            $this->assertTrue(Schema::hasTable($tabla), $tabla.' no fue creada.');
        }

        DB::beginTransaction();
        try {
            $conexion = ConexionWhatsapp::create(['clave_conexion' => 'integracion-principal', 'proveedor' => 'web_autohospedado', 'estado' => 'desconectado']);
            $chat = ChatMonitoreado::create(['conexion_id' => $conexion->id, 'id_chat_proveedor' => 'grupo-prueba@g.us', 'tipo_chat' => 'grupo']);
            $chat->refresh();
            $this->assertFalse($chat->es_monitoreado);
            $this->assertTrue($chat->es_activo);
        } finally {
            DB::rollBack();
        }
    }
}
