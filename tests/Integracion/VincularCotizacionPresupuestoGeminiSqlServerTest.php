<?php

namespace Tests\Integracion;

use App\Data\WhatsApp\EventoWhatsappEntrante;
use App\Models\ChatMonitoreado;
use App\Models\ConexionWhatsapp;
use App\Models\MensajeWhatsapp;
use App\Services\Capture\CapturarEventoWhatsappEntrante;
use App\Services\IA\CotizarCostoReferenciaGemini;
use App\Services\IA\VincularCotizacionPresupuestoGemini;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Puente contable experimental, sin publicador ni Google. Toda la evidencia
 * es sintética, dentro de transacciones reversibles de SQL Server de test.
 */
final class VincularCotizacionPresupuestoGeminiSqlServerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->assertTrue(app()->environment('testing'));
        $this->assertSame('sqlsrv', config('database.default'));
        $this->assertSame('mensajero_integracion_test', config('database.connections.sqlsrv.database'));
        $this->assertSame('mensajero_integracion_test', DB::selectOne('SELECT DB_NAME() AS nombre')->nombre);

        foreach ([
            'mensajero_chats_monitoreados',
            'mensajero_mensajes_whatsapp',
            'mensajero_lotes_gemini',
            'mensajero_mensajes_lote_gemini',
            'mensajero_candado_presupuesto_gemini',
            'mensajero_reservas_consumo_gemini',
        ] as $tabla) {
            $this->assertTrue(Schema::hasTable($tabla), 'Falta la tabla '.$tabla);
        }

        config()->set('mensajero.ia.habilitada', false);
        config()->set('mensajero.ia.envio_autorizado', false);
        config()->set('mensajero.ia.lotes_habilitados', false);
        config()->set('mensajero.tiempo_real.habilitado', false);
        config()->set('mensajero.ia.presupuesto_monetario.habilitado', false);
        Bus::fake();
        Http::preventStrayRequests();
        Http::fake();
    }

    public function test_sin_aprobacion_o_con_cotizacion_insuficiente_no_reserva(): void
    {
        DB::beginTransaction();
        try {
            $grupo = $this->crearGrupo();
            $mensaje = $this->crearMensaje($grupo);
            $loteId = $this->crearLote($grupo, $mensaje);

            $puente = app(VincularCotizacionPresupuestoGemini::class);
            $this->assertSame('deshabilitado', $puente->reservar('lote', $loteId, $grupo->id));

            $this->configuracionDePrueba(319_072, 1_000_000, 1_000_000);
            $this->assertSame('cotizacion_insuficiente', $puente->reservar('lote', $loteId, $grupo->id));

            config()->set('mensajero.ia.presupuesto_monetario.reserva_maxima_microusd', 319_073);
            config()->set('mensajero.ia.presupuesto_monetario.tarifa_referencia', 'otra-tarifa');
            $this->assertSame('deshabilitado', $puente->reservar('lote', $loteId, $grupo->id));

            config()->set('mensajero.ia.presupuesto_monetario.tarifa_referencia',
                CotizarCostoReferenciaGemini::REFERENCIA_TARIFA);
            config()->set('mensajero.ia.modelo', 'gemini-3.6-flash');
            $this->assertSame('deshabilitado', $puente->reservar('lote', $loteId, $grupo->id));

            config()->set('mensajero.ia.modelo', CotizarCostoReferenciaGemini::MODELO);
            config()->set('mensajero.ia.presupuesto_monetario.tarifa_validada', false);
            $this->assertSame('deshabilitado', $puente->reservar('lote', $loteId, $grupo->id));

            $this->assertSame(0, DB::table('mensajero_reservas_consumo_gemini')->count());
            Bus::assertNothingDispatched();
            Http::assertNothingSent();
        } finally {
            DB::rollBack();
        }
    }

    public function test_lote_autentico_solo_de_su_grupo_y_con_cupo_global(): void
    {
        DB::beginTransaction();
        try {
            $grupo1 = $this->crearGrupo();
            $grupo2 = $this->crearGrupo();
            $lote1 = $this->crearLote($grupo1, $this->crearMensaje($grupo1));
            $lote2 = $this->crearLote($grupo2, $this->crearMensaje($grupo2));
            $this->configuracionDePrueba(319_073, 319_073, 319_073);

            $puente = app(VincularCotizacionPresupuestoGemini::class);
            $this->assertSame('fuente_no_valida', $puente->reservar('lote', $lote1, $grupo2->id));
            $this->assertSame('reservada', $puente->reservar('lote', $lote1, $grupo1->id));
            $this->assertSame('ya_reservado', $puente->reservar('lote', $lote1, $grupo1->id));
            $this->assertSame('limite_diario', $puente->reservar('lote', $lote2, $grupo2->id));

            $fila = DB::table('mensajero_reservas_consumo_gemini')
                ->where('tipo_fuente', 'lote')->where('fuente_id', $lote1)->first();
            $this->assertNotNull($fila);
            $this->assertSame(319_073, (int) $fila->importe_reservado_microusd);
            $this->assertSame(CotizarCostoReferenciaGemini::REFERENCIA_TARIFA, $fila->tarifa_referencia);
            $this->assertSame((int) $grupo1->id, (int) $fila->chat_monitoreado_id);
            $this->assertSame(1, DB::table('mensajero_reservas_consumo_gemini')->count());
            Bus::assertNothingDispatched();
            Http::assertNothingSent();
        } finally {
            DB::rollBack();
        }
    }

    public function test_mensaje_individual_y_lote_comparten_el_presupuesto_monetario_global(): void
    {
        DB::beginTransaction();
        try {
            $grupo = $this->crearGrupo();
            $mensajeIndividual = $this->crearMensaje($grupo);
            $mensajeLote = $this->crearMensaje($grupo);
            $loteId = $this->crearLote($grupo, $mensajeLote);
            $this->configuracionDePrueba(319_073, 500_000, 500_000);

            $puente = app(VincularCotizacionPresupuestoGemini::class);
            $this->assertSame('reservada',
                $puente->reservar('mensaje', (int) $mensajeIndividual->id, (int) $grupo->id));
            $this->assertSame('limite_diario', $puente->reservar('lote', $loteId, $grupo->id));
            $this->assertSame(1, DB::table('mensajero_reservas_consumo_gemini')->count());
            $this->assertSame(319_073, (int) DB::table('mensajero_reservas_consumo_gemini')
                ->sum('importe_reservado_microusd'));
            Bus::assertNothingDispatched();
            Http::assertNothingSent();
        } finally {
            DB::rollBack();
        }
    }

    public function test_grupo_revocado_lote_finalizado_y_fuentes_ficticias_son_rechazadas(): void
    {
        DB::beginTransaction();
        try {
            $grupo = $this->crearGrupo();
            $mensaje = $this->crearMensaje($grupo);
            $loteId = $this->crearLote($grupo, $mensaje);
            $this->configuracionDePrueba(319_073, 1_000_000, 1_000_000);

            $puente = app(VincularCotizacionPresupuestoGemini::class);
            $this->assertSame('fuente_no_valida',
                $puente->reservar('mensaje', $loteId + 1_000_000_000, $grupo->id));
            $this->assertSame('datos_invalidos', $puente->reservar('otro', $loteId, $grupo->id));

            DB::table('mensajero_lotes_gemini')->where('id', $loteId)
                ->update(['estado' => 'finalizado']);
            $this->assertSame('fuente_no_valida', $puente->reservar('lote', $loteId, $grupo->id));

            DB::table('mensajero_lotes_gemini')->where('id', $loteId)
                ->update(['estado' => 'reservado']);
            DB::table('mensajero_chats_monitoreados')->where('id', $grupo->id)
                ->update(['es_monitoreado' => false]);
            $this->assertSame('fuente_no_valida', $puente->reservar('lote', $loteId, $grupo->id));
            $this->assertSame(0, DB::table('mensajero_reservas_consumo_gemini')->count());
            Bus::assertNothingDispatched();
            Http::assertNothingSent();
        } finally {
            DB::rollBack();
        }
    }

    public function test_lote_mixto_con_mensaje_de_otro_grupo_no_reserva_presupuesto(): void
    {
        DB::beginTransaction();
        try {
            $grupo = $this->crearGrupo();
            $otroGrupo = $this->crearGrupo();
            $mensajeValido = $this->crearMensaje($grupo);
            $mensajeAjeno = $this->crearMensaje($otroGrupo);
            $loteId = $this->crearLote($grupo, $mensajeValido);

            // La FK valida que el mensaje existe, no que corresponda al
            // grupo declarado por el lote. El puente debe comprobar TODOS.
            DB::table('mensajero_mensajes_lote_gemini')->insert([
                'lote_id' => $loteId,
                'mensaje_whatsapp_id' => $mensajeAjeno->id,
                'posicion' => 2,
            ]);

            $this->configuracionDePrueba(319_073, 1_000_000, 1_000_000);
            $this->assertSame('fuente_no_valida',
                app(VincularCotizacionPresupuestoGemini::class)
                    ->reservar('lote', $loteId, (int) $grupo->id));
            $this->assertSame(0, DB::table('mensajero_reservas_consumo_gemini')->count());
            Bus::assertNothingDispatched();
            Http::assertNothingSent();
        } finally {
            DB::rollBack();
        }
    }

    public function test_lote_con_mensaje_no_normalizado_no_reserva_presupuesto(): void
    {
        DB::beginTransaction();
        try {
            $grupo = $this->crearGrupo();
            $mensaje = $this->crearMensaje($grupo);
            $loteId = $this->crearLote($grupo, $mensaje);

            DB::table('mensajero_mensajes_whatsapp')->where('id', $mensaje->id)
                ->update(['estado_captura' => 'capturado']);

            $this->configuracionDePrueba(319_073, 1_000_000, 1_000_000);
            $this->assertSame('fuente_no_valida',
                app(VincularCotizacionPresupuestoGemini::class)
                    ->reservar('lote', $loteId, (int) $grupo->id));
            $this->assertSame(0, DB::table('mensajero_reservas_consumo_gemini')->count());
            Bus::assertNothingDispatched();
            Http::assertNothingSent();
        } finally {
            DB::rollBack();
        }
    }

    private function configuracionDePrueba(int $importe, int $limiteDiario, int $limiteMensual): void
    {
        // Simulación local, no es consentimiento de pagos ni tarifa aprobada.
        config()->set('mensajero.ia.modelo', CotizarCostoReferenciaGemini::MODELO);
        config()->set('mensajero.ia.presupuesto_monetario.habilitado', true);
        config()->set('mensajero.ia.presupuesto_monetario.tarifa_validada', true);
        config()->set('mensajero.ia.presupuesto_monetario.tarifa_referencia',
            CotizarCostoReferenciaGemini::REFERENCIA_TARIFA);
        config()->set('mensajero.ia.presupuesto_monetario.reserva_maxima_microusd', $importe);
        config()->set('mensajero.ia.presupuesto_monetario.limite_diario_microusd', $limiteDiario);
        config()->set('mensajero.ia.presupuesto_monetario.limite_mensual_microusd', $limiteMensual);
    }

    private function crearGrupo(): ChatMonitoreado
    {
        $sufijo = bin2hex(random_bytes(10));
        $conexion = ConexionWhatsapp::query()->create([
            'clave_conexion' => 'cotizacion-'.$sufijo,
            'proveedor' => 'recolector_whatsapp',
            'estado' => 'conectado',
        ]);

        return ChatMonitoreado::query()->create([
            'conexion_id' => $conexion->id,
            'id_chat_proveedor' => 'cotizacion-'.$sufijo.'@g.us',
            'tipo_chat' => 'grupo',
            'es_monitoreado' => true,
            'es_visible_tablero' => true,
            'es_activo' => true,
        ]);
    }

    private function crearMensaje(ChatMonitoreado $grupo): MensajeWhatsapp
    {
        $idExterno = 'cotizacion-'.bin2hex(random_bytes(12));
        $evento = new EventoWhatsappEntrante(
            conexionId: (int) $grupo->conexion_id,
            chatId: $grupo->id_chat_proveedor,
            tipoEvento: 'mensaje',
            tipoMensaje: 'texto',
            eventoExternoId: $idExterno,
            mensajeProveedorId: $idExterno,
            contenidoCrudo: json_encode([
                'message' => ['conversation' => 'Mensaje ficticio de prueba del presupuesto'],
            ], JSON_THROW_ON_ERROR),
            recibidoEn: CarbonImmutable::now('UTC')->subMinutes(10),
            versionContrato: 'v3-recolector',
        );

        $captura = app(CapturarEventoWhatsappEntrante::class)->ejecutar($evento);
        $this->assertSame('aceptado', $captura->estado);
        $this->assertSame(0, Artisan::call('mensajero:normalizar-lote', [
            'grupo' => $grupo->id,
            '--ids' => (string) $captura->eventoCrudoId,
            '--limite' => 1,
            '--aplicar' => true,
        ]));

        return MensajeWhatsapp::query()
            ->where('evento_crudo_id', $captura->eventoCrudoId)->firstOrFail();
    }

    private function crearLote(ChatMonitoreado $grupo, MensajeWhatsapp $mensaje): int
    {
        $id = (int) DB::table('mensajero_lotes_gemini')->insertGetId([
            'chat_monitoreado_id' => $grupo->id,
            'hash_mensajes' => hash('sha256', bin2hex(random_bytes(16))),
            'estado' => 'reservado',
            'reservado_en' => now('UTC')->format('Y-m-d H:i:s.u'),
        ]);

        DB::table('mensajero_mensajes_lote_gemini')->insert([
            'lote_id' => $id,
            'mensaje_whatsapp_id' => $mensaje->id,
            'posicion' => 1,
        ]);

        return $id;
    }
}
