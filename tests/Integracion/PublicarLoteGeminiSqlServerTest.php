<?php

namespace Tests\Integracion;

use App\Data\WhatsApp\EventoWhatsappEntrante;
use App\Jobs\AnalizarMensajeGeminiTrabajo;
use App\Jobs\AnalizarLoteGeminiTrabajo;
use App\Models\ChatMonitoreado;
use App\Models\ConexionWhatsapp;
use App\Models\MensajeWhatsapp;
use App\Services\Capture\CapturarEventoWhatsappEntrante;
use App\Services\IA\PoliticaEnvioGemini;
use App\Services\IA\PoliticaLotesGemini;
use App\Services\IA\PublicarLoteGemini;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Contracts\Bus\Dispatcher as ContratoDespachador;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/**
 * Valida reserva de un lote, unicidad, presupuesto y aislamiento frente a
 * publicaciones individuales. Redis es simulado con Bus::fake().
 */
final class PublicarLoteGeminiSqlServerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->assertTrue(app()->environment('testing'));
        $this->assertSame('sqlsrv', config('database.default'));
        $this->assertSame('mensajero_integracion_test', config('database.connections.sqlsrv.database'));
        $this->assertSame('mensajero_integracion_test', DB::selectOne('SELECT DB_NAME() AS nombre')->nombre);

        foreach ([
            'mensajero_control_interpretacion_ia',
            'mensajero_lotes_gemini',
            'mensajero_mensajes_lote_gemini',
            'mensajero_control_publicaciones_gemini',
            'mensajero_analisis_operativos_ia',
        ] as $tabla) {
            $this->assertTrue(Schema::hasTable($tabla), 'Falta tabla '.$tabla);
        }

        config()->set('mensajero.tiempo_real.habilitado', false);
        config()->set('mensajero.ia.habilitada', false);
        config()->set('mensajero.ia.envio_autorizado', false);
        config()->set('mensajero.ia.lotes_habilitados', false);

        Bus::fake();
        Http::preventStrayRequests();
        Http::fake();
    }

    public function test_lotes_apagados_no_reservan_ni_publican(): void
    {
        $this->assertSame('deshabilitado', app(PublicarLoteGemini::class)->publicar(99, [123]));
        Bus::assertNothingDispatched();
        Http::assertNothingSent();
    }

    public function test_publica_una_vez_y_repetir_no_duplica_trabajo(): void
    {
        DB::beginTransaction();

        try {
            [$grupo, $mensaje] = $this->prepararGrupoYMensaje();
            $publicador = app(PublicarLoteGemini::class);

            $this->assertSame('publicado', $publicador->publicar($grupo->id, [$mensaje->id]));

            $lotes = DB::table('mensajero_lotes_gemini')
                ->where('chat_monitoreado_id', $grupo->id)->get(['id', 'estado']);
            $this->assertCount(1, $lotes);
            $this->assertSame('publicado', $lotes[0]->estado);
            $loteId = (int) $lotes[0]->id;
            $this->assertSame(1, DB::table('mensajero_mensajes_lote_gemini')
                ->where('lote_id', $loteId)->where('mensaje_whatsapp_id', $mensaje->id)->count());

            Bus::assertDispatched(AnalizarLoteGeminiTrabajo::class,
                static fn (AnalizarLoteGeminiTrabajo $trabajo): bool => $trabajo->loteId === $loteId);
            Bus::assertDispatchedTimes(AnalizarLoteGeminiTrabajo::class, 1);

            $this->assertSame('registrado', $publicador->publicar($grupo->id, [$mensaje->id]));
            Bus::assertDispatchedTimes(AnalizarLoteGeminiTrabajo::class, 1);
            Bus::assertNotDispatched(AnalizarMensajeGeminiTrabajo::class);
            Http::assertNothingSent();
        } finally {
            DB::rollBack();
        }
    }

    public function test_rechaza_mensaje_ya_reservado_por_el_circuito_individual(): void
    {
        DB::beginTransaction();

        try {
            [$grupo, $mensaje] = $this->prepararGrupoYMensaje();

            DB::table('mensajero_control_publicaciones_gemini')->insert([
                'mensaje_whatsapp_id' => $mensaje->id,
                'chat_monitoreado_id' => $grupo->id,
                'estado' => 'reservado',
                'origen' => 'manual',
                'reservado_en' => now('UTC')->format('Y-m-d H:i:s.u'),
            ]);

            $this->assertSame('no_elegible',
                app(PublicarLoteGemini::class)->publicar($grupo->id, [$mensaje->id]));
            $this->assertSame(0, DB::table('mensajero_lotes_gemini')
                ->where('chat_monitoreado_id', $grupo->id)->count());
            Bus::assertNothingDispatched();
            Http::assertNothingSent();
        } finally {
            DB::rollBack();
        }
    }

    public function test_presupuesto_compartido_rechaza_segunda_reserva_del_dia(): void
    {
        DB::beginTransaction();

        try {
            [$grupo, $mensaje] = $this->prepararGrupoYMensaje();
            config()->set('mensajero.ia.conciliacion_maximo_dia_grupo', 1);
            config()->set('mensajero.ia.lotes_maximo_dia_grupo', 1);

            DB::table('mensajero_lotes_gemini')->insert([
                'chat_monitoreado_id' => $grupo->id,
                'hash_mensajes' => hash('sha256', 'reserva-sintetica-anterior'),
                'estado' => 'requiere_revision',
                'reservado_en' => now('UTC')->format('Y-m-d H:i:s.u'),
            ]);

            $this->assertSame('limite_diario',
                app(PublicarLoteGemini::class)->publicar($grupo->id, [$mensaje->id]));
            $this->assertSame(1, DB::table('mensajero_lotes_gemini')
                ->where('chat_monitoreado_id', $grupo->id)->count());
            Bus::assertNothingDispatched();
            Http::assertNothingSent();
        } finally {
            DB::rollBack();
        }
    }

    public function test_fallo_de_publicacion_incierta_exige_revision_y_nunca_republica(): void
    {
        DB::beginTransaction();

        try {
            [$grupo, $mensaje] = $this->prepararGrupoYMensaje();

            // El transporte puede haber aceptado el trabajo antes de informar
            // un error. Simularlo en el contrato de Bus SIN tocar Redis real.
            $despachos = 0;
            $despachador = Mockery::mock(ContratoDespachador::class);
            $despachador->shouldReceive('dispatch')->once()
                ->with(Mockery::on(static fn ($trabajo): bool =>
                    $trabajo instanceof AnalizarLoteGeminiTrabajo))
                ->andReturnUsing(static function () use (&$despachos): never {
                    $despachos++;
                    throw new RuntimeException('Resultado incierto de Redis (simulado)');
                });
            Bus::swap($despachador);

            $publicador = app(PublicarLoteGemini::class);
            try {
                $publicador->publicar($grupo->id, [$mensaje->id]);
                $this->fail('Una publicación incierta debe impedir confirmar el éxito.');
            } catch (RuntimeException $error) {
                $this->assertStringContainsString('incierta', $error->getMessage());
            }

            $lote = DB::table('mensajero_lotes_gemini')
                ->where('chat_monitoreado_id', $grupo->id)->first();
            $this->assertNotNull($lote);
            $this->assertSame('requiere_revision', $lote->estado);
            $this->assertSame('RuntimeException', $lote->error_tipo);
            $this->assertSame(1, $despachos);

            // La marca y la reserva persistentes bloquean cualquier repetición.
            $this->assertSame('registrado', $publicador->publicar($grupo->id, [$mensaje->id]));
            $this->assertSame(1, $despachos);
            $this->assertSame(1, DB::table('mensajero_mensajes_lote_gemini')
                ->where('lote_id', $lote->id)->count());
            Http::assertNothingSent();
        } finally {
            DB::rollBack();
        }
    }

    public function test_fallo_tardio_de_publicacion_no_revierte_lote_ya_finalizado(): void
    {
        DB::beginTransaction();

        try {
            [$grupo, $mensaje] = $this->prepararGrupoYMensaje();

            // Un consumidor muy rápido pudo finalizar ANTES de que la llamada
            // de publicación informara un fallo al proceso solicitante.
            $despachos = 0;
            $despachador = Mockery::mock(ContratoDespachador::class);
            $despachador->shouldReceive('dispatch')->once()
                ->andReturnUsing(static function () use (&$despachos, $grupo): never {
                    $despachos++;
                    $lote = DB::table('mensajero_lotes_gemini')
                        ->where('chat_monitoreado_id', $grupo->id)->first();
                    if ($lote === null || $lote->estado !== 'publicado') {
                        throw new RuntimeException('Estado inesperado antes de terminar el lote sintético.');
                    }
                    DB::table('mensajero_lotes_gemini')
                        ->where('id', $lote->id)
                        ->update([
                            'estado' => 'finalizado',
                            'finalizado_en' => now('UTC')->format('Y-m-d H:i:s.u'),
                        ]);
                    throw new RuntimeException('Error tardío de Redis (simulado)');
                });
            Bus::swap($despachador);

            $publicador = app(PublicarLoteGemini::class);
            try {
                $publicador->publicar($grupo->id, [$mensaje->id]);
                $this->fail('El error tardío del transporte no debe ocultarse.');
            } catch (RuntimeException $error) {
                $this->assertStringContainsString('incierta', $error->getMessage());
            }

            $lote = DB::table('mensajero_lotes_gemini')
                ->where('chat_monitoreado_id', $grupo->id)->first();
            $this->assertNotNull($lote);
            $this->assertSame('finalizado', $lote->estado);
            $this->assertNull($lote->error_tipo);
            $this->assertSame(1, $despachos);

            $this->assertSame('registrado', $publicador->publicar($grupo->id, [$mensaje->id]));
            $this->assertSame(1, $despachos);
            Http::assertNothingSent();
        } finally {
            DB::rollBack();
        }
    }

    /**
     * @return array{0:ChatMonitoreado,1:MensajeWhatsapp}
     */
    private function prepararGrupoYMensaje(): array
    {
        $clave = bin2hex(random_bytes(12));
        $conexion = ConexionWhatsapp::query()->create([
            'clave_conexion' => 'revocacion-'.$clave,
            'proveedor' => 'recolector_whatsapp',
            'estado' => 'conectado',
        ]);

        $grupo = ChatMonitoreado::query()->create([
            'conexion_id' => $conexion->id,
            'id_chat_proveedor' => 'revocacion-'.$clave.'@g.us',
            'tipo_chat' => 'grupo',
            'es_monitoreado' => true,
            'es_visible_tablero' => true,
            'es_activo' => true,
        ]);

        $inicio = CarbonImmutable::now('UTC')->subHours(2);
        $fechaMensaje = CarbonImmutable::now('UTC')->subMinutes(10);
        $corteSql = $inicio->format('Y-m-d H:i:s.u');

        $idExterno = 'revocacion-'.$clave;
        $evento = new EventoWhatsappEntrante(
            conexionId: (int) $conexion->id,
            chatId: $grupo->id_chat_proveedor,
            tipoEvento: 'mensaje',
            tipoMensaje: 'texto',
            eventoExternoId: $idExterno,
            mensajeProveedorId: $idExterno,
            contenidoCrudo: json_encode([
                'message' => ['conversation' => 'Mensaje sintetico para probar revocacion de autorizaciones'],
            ], JSON_THROW_ON_ERROR),
            recibidoEn: $fechaMensaje,
            versionContrato: 'v3-recolector',
        );

        $resultado = app(CapturarEventoWhatsappEntrante::class)->ejecutar($evento);
        $this->assertSame('aceptado', $resultado->estado);
        $this->assertSame(0, Artisan::call('mensajero:normalizar-lote', [
            'grupo' => $grupo->id,
            '--ids' => (string) $resultado->eventoCrudoId,
            '--limite' => 1,
            '--aplicar' => true,
        ]));

        $mensaje = MensajeWhatsapp::query()->where('evento_crudo_id', $resultado->eventoCrudoId)->firstOrFail();
        // Confirmar que preparar la evidencia NO publicó al circuito individual.
        $this->assertFalse(DB::table('mensajero_control_publicaciones_gemini')
            ->where('mensaje_whatsapp_id', $mensaje->id)->exists());
        Bus::assertNotDispatched(AnalizarMensajeGeminiTrabajo::class);
        Bus::assertNotDispatched(AnalizarLoteGeminiTrabajo::class);
        Http::assertNothingSent();

        // Los permisos se habilitan solo DESPUES de normalizar el mensaje.
        // Así esta prueba cubre únicamente el circuito de lotes.
        config()->set('mensajero.ia.habilitada', true);
        config()->set('mensajero.ia.envio_autorizado', true);
        config()->set('mensajero.ia.servicio_pagado_confirmado', true);
        config()->set('mensajero.ia.clave_api', 'clave-ficticia-sin-acceso-a-google');
        config()->set('mensajero.ia.fecha_inicio', $inicio->format('Y-m-d\\TH:i:sP'));
        config()->set('mensajero.ia.lotes_habilitados', true);
        config()->set('mensajero.ia.lotes_fecha_inicio', $inicio->format('Y-m-d\\TH:i:sP'));
        config()->set('mensajero.ia.lotes_maximo_dia_grupo', 3);

        DB::table('mensajero_control_interpretacion_ia')
            ->where('chat_monitoreado_id', 0)
            ->update(['habilitado' => true, 'inicio_en' => $corteSql]);

        DB::table('mensajero_control_interpretacion_ia')->insert([
            'chat_monitoreado_id' => $grupo->id,
            'habilitado' => true,
            'inicio_en' => $corteSql,
            'actualizado_en' => $corteSql,
        ]);

        $this->assertTrue(app(PoliticaEnvioGemini::class)->configuracionCompleta());
        $this->assertTrue(app(PoliticaLotesGemini::class)->habilitada());

        $this->assertTrue(app(PoliticaEnvioGemini::class)->autoriza($grupo->id, $mensaje->recibido_en));
        $this->assertTrue(app(PoliticaLotesGemini::class)->posteriorAlCorte($mensaje->recibido_en));

        return [$grupo, $mensaje];
    }

}
