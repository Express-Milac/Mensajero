<?php

namespace Tests\Integracion;

use App\Models\ChatMonitoreado;
use App\Models\ConexionWhatsapp;
use App\Services\IA\ControlInterpretacionGemini;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ControlInterpretacionGeminiSqlServerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlsrv', config('database.default'));
        $this->assertStringEndsWith('_test', (string) config('database.connections.sqlsrv.database'));
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
    }

    public function test_el_panel_apaga_trabajos_y_cada_reactivacion_impide_historico(): void
    {
        DB::beginTransaction();
        try {
            $conexion = ConexionWhatsapp::query()->create([
                'clave_conexion' => 'ia-control-'.bin2hex(random_bytes(6)),
                'proveedor' => 'recolector_whatsapp',
                'estado' => 'conectado',
            ]);
            $grupo = ChatMonitoreado::query()->create([
                'conexion_id' => $conexion->id,
                'id_chat_proveedor' => bin2hex(random_bytes(8)).'@g.us',
                'nombre_grupo' => 'Grupo ficticio para prueba SQL',
                'tipo_chat' => 'grupo',
                'es_monitoreado' => true,
                'es_visible_tablero' => true,
                'es_activo' => true,
            ]);
            $id = (int) $grupo->id;
            config([
                'mensajero.ia.habilitada' => true,
                'mensajero.ia.envio_autorizado' => true,
                'mensajero.ia.servicio_pagado_confirmado' => true,
                'mensajero.ia.clave_api' => 'clave-ficticia-pruebas',
                'mensajero.ia.fecha_inicio' => '2026-10-21T00:00:00-06:00',
                'mensajero.ia.grupos_autorizados' => [$id],
            ]);

            $control = app(ControlInterpretacionGemini::class);
            $this->assertTrue($control->disponible());
            $this->assertFalse($control->permite($id, CarbonImmutable::now('UTC')));

            $control->cambiar($id, true, 'prueba_sql', null);
            $this->assertFalse($control->permite($id, CarbonImmutable::now('UTC')));

            $control->cambiar(0, true, 'prueba_sql', null);
            $this->assertTrue($control->permite($id, CarbonImmutable::now('UTC')));
            $this->assertFalse($control->permite($id, CarbonImmutable::now('UTC')->subMinutes(5)));

            $control->cambiar($id, false, 'prueba_sql', null);
            $this->assertFalse($control->permite($id, CarbonImmutable::now('UTC')));
            $control->cambiar($id, true, 'prueba_sql', null);
            $this->assertTrue($control->permite($id, CarbonImmutable::now('UTC')));
            $this->assertFalse($control->permite($id, CarbonImmutable::now('UTC')->subMinute()));

            $control->cambiar(0, false, 'prueba_sql', null);
            $this->assertFalse($control->permite($id, CarbonImmutable::now('UTC')));
            $this->assertGreaterThanOrEqual(5, DB::table('mensajero_auditoria_interpretacion_ia')
                ->whereIn('chat_monitoreado_id', [0, $id])->count());
        } finally {
            DB::rollBack();
        }
    }
}
