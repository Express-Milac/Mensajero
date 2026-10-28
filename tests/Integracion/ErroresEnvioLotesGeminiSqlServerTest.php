<?php

namespace Tests\Integracion;

use App\Data\WhatsApp\EventoWhatsappEntrante;
use App\Jobs\AnalizarLoteGeminiTrabajo;
use App\Jobs\AnalizarMensajeGeminiTrabajo;
use App\Models\ChatMonitoreado;
use App\Models\ConexionWhatsapp;
use App\Models\MensajeWhatsapp;
use App\Services\Capture\CapturarEventoWhatsappEntrante;
use App\Services\IA\PoliticaEnvioGemini;
use App\Services\IA\PoliticaLotesGemini;
use App\Services\IA\ProcesarLoteGemini;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * Simula respuestas HTTP de Gemini sin red ni Redis para probar no-reintento.
 * Se ejecuta solo contra mensajero_integracion_test, con rollback SQL.
 */
final class ErroresEnvioLotesGeminiSqlServerTest extends TestCase
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
        ] as $tabla) {
            $this->assertTrue(Schema::hasTable($tabla), 'Falta tabla: '.$tabla);
        }

        // Sin notificacion al canal vivo ni despacho del circuito individual.
        config()->set('mensajero.tiempo_real.habilitado', false);
        config()->set('mensajero.ia.habilitada', false);
        config()->set('mensajero.ia.envio_autorizado', false);
        config()->set('mensajero.ia.lotes_habilitados', false);
        Bus::fake();
        Http::preventStrayRequests();
    }

    public function test_http_503_pasa_a_revision_y_segunda_ejecucion_no_reenvia(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response(
                ['error' => ['status' => 'UNAVAILABLE']], 503
            ),
        ]);

        $this->validarErrorExternoSinReintento('HTTP 503');
    }

    public function test_respuesta_incompleta_pasa_a_revision_y_no_reenvia(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['finishReason' => 'MAX_TOKENS']],
            ], 200),
        ]);

        $this->validarErrorExternoSinReintento('no finalizó');
    }

    private function validarErrorExternoSinReintento(string $textoError): void
    {
        DB::beginTransaction();

        try {
            [, , $loteId] = $this->prepararLotePublicado();
            $this->assertSame('publicado', DB::table('mensajero_lotes_gemini')
                ->where('id', $loteId)->value('estado'));

            try {
                app(ProcesarLoteGemini::class)->ejecutar($loteId);
                $this->fail('Un error externo debe lanzar excepcion y requerir revision.');
            } catch (RuntimeException $error) {
                $this->assertStringContainsString($textoError, $error->getMessage());
            }

            $this->assertSame('requiere_revision', DB::table('mensajero_lotes_gemini')
                ->where('id', $loteId)->value('estado'));
            $this->assertSame('RuntimeException', DB::table('mensajero_lotes_gemini')
                ->where('id', $loteId)->value('error_tipo'));

            Http::assertSentCount(1);
            Http::assertSent(static fn ($request): bool => $request->method() === 'POST'
                && str_starts_with($request->url(), 'https://generativelanguage.googleapis.com/'));

            // Como el lote ya no esta publicado, no puede volver a contactar Google.
            $this->assertSame('omitido', app(ProcesarLoteGemini::class)->ejecutar($loteId));
            Http::assertSentCount(1);

            Bus::assertNotDispatched(AnalizarMensajeGeminiTrabajo::class);
            Bus::assertNotDispatched(AnalizarLoteGeminiTrabajo::class);
        } finally {
            DB::rollBack();
        }
    }

    /**
     * @return array{0:ChatMonitoreado,1:MensajeWhatsapp,2:int}
     */
    private function prepararLotePublicado(): array
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

        $loteId = (int) DB::table('mensajero_lotes_gemini')->insertGetId([
            'chat_monitoreado_id' => $grupo->id,
            'hash_mensajes' => hash('sha256', $clave),
            'estado' => 'publicado',
            'reservado_en' => now('UTC')->format('Y-m-d H:i:s.u'),
            'publicado_en' => now('UTC')->format('Y-m-d H:i:s.u'),
        ]);

        DB::table('mensajero_mensajes_lote_gemini')->insert([
            'lote_id' => $loteId,
            'mensaje_whatsapp_id' => $mensaje->id,
            'posicion' => 1,
        ]);

        return [$grupo, $mensaje, $loteId];
    }
}
