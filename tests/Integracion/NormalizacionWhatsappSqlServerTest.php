<?php

namespace Tests\Integracion;

use App\Data\WhatsApp\EventoWhatsappEntrante;
use App\Jobs\NormalizarEventoWhatsappTrabajo;
use App\Models\ChatMonitoreado;
use App\Models\ConexionWhatsapp;
use App\Models\ContactoWhatsapp;
use App\Models\EventoCrudoWhatsapp;
use App\Models\MensajeBuzonSalida;
use App\Models\MensajeWhatsapp;
use App\Services\Capture\CapturarEventoWhatsappEntrante;
use App\Services\Normalizacion\DespacharBuzonWhatsapp;
use App\Services\Normalizacion\NormalizarEventoWhatsapp;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NormalizacionWhatsappSqlServerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('sqlsrv', config('database.default'));
        $this->assertStringEndsWith('_test', (string) config('database.connections.sqlsrv.database'));
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
    }

    public function test_normaliza_sin_duplicados_y_resuelve_citas_fuera_de_orden(): void
    {
        DB::beginTransaction();

        try {
            [$conexion, $chat, $sufijo] = $this->entorno();
            $remitente = '521000000001@s.whatsapp.net';
            $idOriginal = 'original-'.$sufijo;

            $respuesta = $this->capturar($conexion, $chat, 'respuesta-'.$sufijo, [
                'message' => ['extendedTextMessage' => ['text' => 'Recibido']],
            ], remitente: $remitente, citado: $idOriginal);

            $normalizador = app(NormalizarEventoWhatsapp::class);
            $this->assertSame('normalizado', $normalizador->ejecutar((int) $respuesta->id));
            $this->assertSame('duplicado', $normalizador->ejecutar((int) $respuesta->id));

            $mensajeRespuesta = MensajeWhatsapp::query()->where('evento_crudo_id', $respuesta->id)->firstOrFail();
            $this->assertSame('Recibido', $mensajeRespuesta->contenido_texto);
            $this->assertSame($idOriginal, $mensajeRespuesta->id_mensaje_citado_proveedor);
            $this->assertNull($mensajeRespuesta->mensaje_citado_id);
            $this->assertSame('UTC', $mensajeRespuesta->recibido_en->timezoneName);

            $original = $this->capturar($conexion, $chat, $idOriginal, [
                'message' => ['conversation' => 'Unidad disponible'],
                'messageTimestamp' => 1792429200,
            ], remitente: $remitente);

            $this->assertSame('normalizado', $normalizador->ejecutar((int) $original->id));
            $this->assertSame('duplicado', $normalizador->ejecutar((int) $original->id));

            $mensajeOriginal = MensajeWhatsapp::query()->where('evento_crudo_id', $original->id)->firstOrFail();
            $this->assertSame('Unidad disponible', $mensajeOriginal->contenido_texto);
            $this->assertNotNull($mensajeOriginal->enviado_en);
            $this->assertSame('no_requerido', $mensajeOriginal->estado_multimedia);
            $this->assertSame((int) $mensajeOriginal->id, (int) $mensajeRespuesta->fresh()->mensaje_citado_id);

            $this->assertSame(2, MensajeWhatsapp::query()->where('conexion_id', $conexion->id)->count());
            $this->assertSame(1, ContactoWhatsapp::query()->where('conexion_id', $conexion->id)->count());
            $this->assertSame(2, EventoCrudoWhatsapp::query()
                ->where('conexion_id', $conexion->id)
                ->where('estado_procesamiento', 'normalizado')
                ->count());
        } finally {
            DB::rollBack();
        }
    }

    public function test_edicion_se_conserva_para_procesamiento_semantico_sin_crear_mensaje_falso(): void
    {
        DB::beginTransaction();

        try {
            [$conexion, $chat, $sufijo] = $this->entorno();

            $edicion = $this->capturar($conexion, $chat, 'edicion-'.$sufijo, [
                'message' => ['protocolMessage' => ['type' => 14]],
            ], tipoEvento: 'edicion', objetivo: 'original-'.$sufijo);

            $normalizador = app(NormalizarEventoWhatsapp::class);
            $this->assertSame('pendiente_semantica', $normalizador->ejecutar((int) $edicion->id));
            $this->assertSame('duplicado', $normalizador->ejecutar((int) $edicion->id));
            $this->assertSame('pendiente_semantica', $edicion->fresh()->estado_procesamiento);
            $this->assertSame(0, MensajeWhatsapp::query()->where('conexion_id', $conexion->id)->count());
        } finally {
            DB::rollBack();
        }
    }

    public function test_buzon_publica_a_redis_una_vez_y_no_elimina_la_evidencia(): void
    {
        DB::beginTransaction();

        try {
            [$conexion, $chat, $sufijo] = $this->entorno();
            $evento = $this->capturar($conexion, $chat, 'buzon-'.$sufijo, [
                'message' => ['conversation' => 'Entregado'],
            ]);

            Queue::fake();
            $despachador = app(DespacharBuzonWhatsapp::class);
            $this->assertSame(['publicados' => 1, 'fallidos' => 0], $despachador->ejecutar(1, (int) $evento->id));
            $this->assertSame(['publicados' => 0, 'fallidos' => 0], $despachador->ejecutar(1, (int) $evento->id));
            Queue::assertPushedOn('mensajero_normalizacion', NormalizarEventoWhatsappTrabajo::class);

            $buzon = MensajeBuzonSalida::query()
                ->where('tipo_entidad', 'evento_crudo_whatsapp')
                ->where('entidad_id', $evento->id)
                ->firstOrFail();

            $this->assertNotNull($buzon->enviado_en);
            $this->assertNull($buzon->reservado_hasta);
            $this->assertSame('capturado', $evento->fresh()->estado_procesamiento);

            // El trabajo consumido por Redis puede reentregarse sin crear copias.
            app(NormalizarEventoWhatsapp::class)->ejecutar((int) $evento->id);
            app(NormalizarEventoWhatsapp::class)->ejecutar((int) $evento->id);
            $this->assertSame(1, MensajeWhatsapp::query()->where('evento_crudo_id', $evento->id)->count());
        } finally {
            DB::rollBack();
        }
    }

    /**
     * @return array{0: ConexionWhatsapp, 1: ChatMonitoreado, 2: string}
     */
    private function entorno(): array
    {
        $sufijo = bin2hex(random_bytes(6));
        $conexion = ConexionWhatsapp::query()->create([
            'clave_conexion' => 'normalizacion-'.$sufijo,
            'proveedor' => 'recolector_whatsapp',
            'estado' => 'conectado',
        ]);
        $chat = ChatMonitoreado::query()->create([
            'conexion_id' => $conexion->id,
            'id_chat_proveedor' => 'normalizacion-'.$sufijo.'@g.us',
            'tipo_chat' => 'grupo',
            'es_monitoreado' => true,
            'es_activo' => true,
        ]);

        return [$conexion, $chat, $sufijo];
    }

    /**
     * @param  array<string, mixed>  $contenido
     */
    private function capturar(
        ConexionWhatsapp $conexion,
        ChatMonitoreado $chat,
        string $idExterno,
        array $contenido,
        ?string $remitente = null,
        ?string $citado = null,
        string $tipoEvento = 'mensaje',
        ?string $objetivo = null,
    ): EventoCrudoWhatsapp {
        $captura = app(CapturarEventoWhatsappEntrante::class)->ejecutar(new EventoWhatsappEntrante(
            conexionId: (int) $conexion->id,
            chatId: $chat->id_chat_proveedor,
            tipoEvento: $tipoEvento,
            tipoMensaje: 'texto',
            eventoExternoId: $idExterno,
            mensajeProveedorId: $idExterno,
            contenidoCrudo: json_encode($contenido, JSON_THROW_ON_ERROR),
            recibidoEn: CarbonImmutable::now('UTC'),
            versionContrato: 'v3-recolector',
            remitenteProveedorId: $remitente,
            mensajeCitadoProveedorId: $citado,
            mensajeObjetivoProveedorId: $objetivo,
        ));

        $this->assertSame('aceptado', $captura->estado);

        return EventoCrudoWhatsapp::query()->findOrFail($captura->eventoCrudoId);
    }
}
