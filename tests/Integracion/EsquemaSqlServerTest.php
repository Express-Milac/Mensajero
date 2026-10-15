<?php

namespace Tests\Integracion;

use Illuminate\Support\Facades\Schema;
use Tests\CasoPrueba;

class EsquemaSqlServerTest extends CasoPrueba
{
    public function test_esquema_de_mensajero_usa_solo_tablas_y_columnas_propias_en_espanol(): void
    {
        $this->assertSame('sqlsrv', config('database.default'));
        $this->assertStringEndsWith('_test', config('database.connections.sqlsrv.database'), 'Use una base desechable terminada en _test.');

        $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();

        $tablas = [
            'mensajero_migraciones',
            'mensajero_usuarios',
            'mensajero_tokens_restablecimiento',
            'mensajero_conexiones_whatsapp',
            'mensajero_conversaciones_monitoreadas',
            'mensajero_contactos_whatsapp',
            'mensajero_eventos_crudos_whatsapp',
            'mensajero_bandeja_salida',
            'mensajero_mensajes_whatsapp',
        ];

        foreach ($tablas as $tabla) {
            $this->assertTrue(Schema::hasTable($tabla), "No existe la tabla esperada: $tabla");
        }

        foreach ([
            'mensajero_users',
            'mensajero_whatsapp_connections',
            'mensajero_monitored_chats',
            'mensajero_whatsapp_contacts',
            'mensajero_whatsapp_raw_events',
            'mensajero_outbox',
            'mensajero_whatsapp_messages',
            'mensajero_cache',
            'mensajero_jobs',
        ] as $tablaAntigua) {
            $this->assertFalse(Schema::hasTable($tablaAntigua), "Permanece una tabla antigua: $tablaAntigua");
        }

        $this->assertTrue(Schema::hasColumns('mensajero_eventos_crudos_whatsapp', [
            'conexion_id',
            'conversacion_monitoreada_id',
            'clave_deduplicacion',
            'contenido_crudo',
            'estado_procesamiento',
            'recibido_en',
        ]));

        $this->assertTrue(Schema::hasColumns('mensajero_mensajes_whatsapp', [
            'id_mensaje_proveedor',
            'tipo_mensaje',
            'contenido_texto',
            'estado_multimedia',
            'estado_ia',
        ]));
    }
}
