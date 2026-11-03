<?php

namespace Tests\Integracion;

use App\Data\WhatsApp\EventoWhatsappEntrante;
use App\Jobs\NormalizarEventoWhatsappTrabajo;
use App\Models\ChatMonitoreado;
use App\Models\ConexionWhatsapp;
use App\Models\EventoCrudoWhatsapp;
use App\Models\MensajeBuzonSalida;
use App\Services\Capture\CapturarEventoWhatsappEntrante;
use App\Services\IA\ControlInterpretacionGemini;
use App\Services\Normalizacion\DespacharBuzonWhatsapp;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class RecuperacionLocalHistoricaSqlServerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlsrv', config('database.default'));
        $this->assertStringEndsWith('_test', (string) config('database.connections.sqlsrv.database'));
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
    }

    public function test_reserva_solo_historico_del_grupo_con_trabajo_solo_local(): void
    {
        DB::beginTransaction();

        try {
            $grupo = $this->crearGrupo();
            $otroGrupo = $this->crearGrupo();
            $antiguo = $this->crearEvento($grupo);
            $posterior = $this->crearEvento($grupo);
            $ajeno = $this->crearEvento($otroGrupo);
            $ahora = CarbonImmutable::now('UTC');
            $corte = $ahora->subMinutes(2)->format('Y-m-d H:i:s.u');

            DB::table('mensajero_eventos_crudos_whatsapp')
                ->whereIn('id', [$antiguo->id, $ajeno->id])
                ->update(['recibido_en' => $ahora->subMinutes(5)->format('Y-m-d H:i:s.u')]);

            Queue::fake();
            $resultado = app(DespacharBuzonWhatsapp::class)->ejecutar(
                20, null, (int) $grupo->id, null, true, $corte
            );

            $this->assertSame(['publicados' => 1, 'fallidos' => 0], $resultado);
            Queue::assertPushed(NormalizarEventoWhatsappTrabajo::class, function ($trabajo) use ($antiguo) {
                return $trabajo->eventoCrudoId === (int) $antiguo->id
                    && $trabajo->soloLocal === true;
            });

            $this->assertNotNull(MensajeBuzonSalida::query()
                ->where('entidad_id', $antiguo->id)->firstOrFail()->enviado_en);
            $this->assertNull(MensajeBuzonSalida::query()
                ->where('entidad_id', $posterior->id)->firstOrFail()->enviado_en);
            $this->assertNull(MensajeBuzonSalida::query()
                ->where('entidad_id', $ajeno->id)->firstOrFail()->enviado_en);
        } finally {
            DB::rollBack();
        }
    }

    public function test_vista_previa_no_publica_y_aplicacion_publica_solo_local(): void
    {
        DB::beginTransaction();

        try {
            $grupo = $this->crearGrupo();
            $evento = $this->crearEvento($grupo);
            DB::table('mensajero_eventos_crudos_whatsapp')
                ->where('id', $evento->id)
                ->update(['recibido_en' => CarbonImmutable::now('UTC')->subDay()->format('Y-m-d H:i:s.u')]);

            config([
                'mensajero.normalizacion.habilitada' => true,
                'mensajero.ia.habilitada' => true,
                'mensajero.ia.envio_autorizado' => true,
                'mensajero.ia.servicio_pagado_confirmado' => true,
                'mensajero.ia.clave_api' => 'clave-ficticia-pruebas',
                'mensajero.ia.fecha_inicio' => '2026-10-21T00:00:00-06:00',
            ]);

            $control = app(ControlInterpretacionGemini::class);
            $control->cambiar((int) $grupo->id, true, 'prueba_sql', null);
            $control->cambiar(0, true, 'prueba_sql', null);

            Queue::fake();
            $params = ['grupo' => $grupo->id, '--limite' => 3];

            $this->assertSame(0, Artisan::call('mensajero:recuperar-historico-local', $params));
            Queue::assertNothingPushed();
            $this->assertNull(MensajeBuzonSalida::query()
                ->where('entidad_id', $evento->id)->firstOrFail()->enviado_en);

            $this->assertSame(0, Artisan::call('mensajero:recuperar-historico-local',
                $params + ['--aplicar' => true]
            ), Artisan::output());

            Queue::assertPushed(NormalizarEventoWhatsappTrabajo::class, function ($trabajo) use ($evento) {
                return $trabajo->eventoCrudoId === (int) $evento->id
                    && $trabajo->soloLocal === true;
            });
        } finally {
            DB::rollBack();
        }
    }

    public function test_rechaza_recuperacion_sin_corte_gemini(): void
    {
        DB::beginTransaction();

        try {
            $grupo = $this->crearGrupo();
            $evento = $this->crearEvento($grupo);
            config(['mensajero.normalizacion.habilitada' => true]);
            Queue::fake();

            $this->assertSame(1, Artisan::call('mensajero:recuperar-historico-local', [
                'grupo' => $grupo->id,
                '--aplicar' => true,
            ]));
            Queue::assertNothingPushed();
            $this->assertNull(MensajeBuzonSalida::query()
                ->where('entidad_id', $evento->id)->firstOrFail()->enviado_en);
        } finally {
            DB::rollBack();
        }
    }

    private function crearGrupo(): ChatMonitoreado
    {
        $sufijo = bin2hex(random_bytes(8));
        $conexion = ConexionWhatsapp::query()->create([
            'clave_conexion' => 'ensayo-historico-'.$sufijo,
            'proveedor' => 'recolector_whatsapp',
            'estado' => 'conectado',
        ]);

        return ChatMonitoreado::query()->create([
            'conexion_id' => $conexion->id,
            'id_chat_proveedor' => 'ensayo-historico-'.$sufijo.'@g.us',
            'tipo_chat' => 'grupo',
            'es_monitoreado' => true,
            'es_visible_tablero' => true,
            'es_activo' => true,
        ]);
    }

    private function crearEvento(ChatMonitoreado $grupo): EventoCrudoWhatsapp
    {
        $clave = 'historico-prueba-'.bin2hex(random_bytes(8));
        $resultado = app(CapturarEventoWhatsappEntrante::class)->ejecutar(new EventoWhatsappEntrante(
            (int) $grupo->conexion_id,
            $grupo->id_chat_proveedor,
            'mensaje',
            'texto',
            $clave,
            $clave,
            json_encode(['message' => ['conversation' => 'Mensaje ficticio para ensayo']], JSON_THROW_ON_ERROR),
            CarbonImmutable::now('UTC'),
            'v3-recolector'
        ));
        $this->assertSame('aceptado', $resultado->estado);

        return EventoCrudoWhatsapp::query()->findOrFail($resultado->eventoCrudoId);
    }
}
