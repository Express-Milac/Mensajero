<?php

namespace Tests\Integracion;

use App\Data\WhatsApp\EventoWhatsappEntrante;
use App\Models\ChatMonitoreado;
use App\Models\ConexionWhatsapp;
use App\Models\EventoCrudoWhatsapp;
use App\Models\MensajeBuzonSalida;
use App\Services\Capture\CapturarEventoWhatsappEntrante;
use App\Services\Normalizacion\NormalizarEventoWhatsapp;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RecuperacionPublicacionSqlServerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('sqlsrv', config('database.default'));
        $this->assertStringEndsWith('_test', (string) config('database.connections.sqlsrv.database'));
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
    }

    public function test_previsualiza_sin_escribir_y_recupera_unicamente_un_aviso_antiguo(): void
    {
        DB::beginTransaction();

        try {
            [$grupo, $evento] = $this->crearEvento();

            $buzon = MensajeBuzonSalida::query()->where('entidad_id', $evento->id)->firstOrFail();
            $buzon->enviado_en = now('UTC')->subMinutes(60);
            $buzon->save();

            $antes = $buzon->fresh()->enviado_en->format('Y-m-d H:i:s.u');
            $huella = $evento->hash_contenido_sha256;
            $otroEvento = $this->crearEventoEnGrupo($grupo);
            $argumentos = ['evento' => $evento->id, '--minutos' => 30];

            $this->assertSame(0, Artisan::call('mensajero:recuperar-publicacion', $argumentos));
            $this->assertSame($antes, $buzon->fresh()->enviado_en->format('Y-m-d H:i:s.u'));

            $this->assertSame(0, Artisan::call('mensajero:recuperar-publicacion', $argumentos + [
                '--aplicar' => true,
            ]));

            $this->assertNull($buzon->fresh()->enviado_en);
            $this->assertSame('capturado', $evento->fresh()->estado_procesamiento);
            $this->assertSame($huella, $evento->fresh()->hash_contenido_sha256);
            $this->assertNull(MensajeBuzonSalida::query()->where('entidad_id', $otroEvento->id)->firstOrFail()->enviado_en);
            $this->assertSame(1, Artisan::call('mensajero:recuperar-publicacion', $argumentos + [
                '--aplicar' => true,
            ]));
        } finally {
            DB::rollBack();
        }
    }

    public function test_no_recupera_aviso_reciente_o_mensaje_normalizado(): void
    {
        DB::beginTransaction();

        try {
            [$grupo, $evento] = $this->crearEvento();
            $buzon = MensajeBuzonSalida::query()->where('entidad_id', $evento->id)->firstOrFail();
            $buzon->enviado_en = now('UTC')->subMinutes(5);
            $buzon->save();

            $this->assertSame(1, Artisan::call('mensajero:recuperar-publicacion', [
                'evento' => $evento->id,
                '--aplicar' => true,
            ]));
            $this->assertNotNull($buzon->fresh()->enviado_en);

            $this->assertSame('normalizado', app(NormalizarEventoWhatsapp::class)->ejecutar((int) $evento->id));
            $buzon->enviado_en = now('UTC')->subMinutes(60);
            $buzon->save();

            $this->assertSame(1, Artisan::call('mensajero:recuperar-publicacion', [
                'evento' => $evento->id,
                '--aplicar' => true,
            ]));
            $this->assertNotNull($buzon->fresh()->enviado_en);
        } finally {
            DB::rollBack();
        }
    }

    public function test_bloquea_grupo_no_autorizado_y_base_no_test(): void
    {
        DB::beginTransaction();

        try {
            [$grupo, $evento] = $this->crearEvento();
            $buzon = MensajeBuzonSalida::query()->where('entidad_id', $evento->id)->firstOrFail();
            $buzon->enviado_en = now('UTC')->subMinutes(60);
            $buzon->save();
            $grupo->update(['es_activo' => false]);

            $this->assertSame(1, Artisan::call('mensajero:recuperar-publicacion', [
                'evento' => $evento->id,
                '--aplicar' => true,
            ]));
            $this->assertNotNull($buzon->fresh()->enviado_en);

            $original = config('database.connections.sqlsrv.database');

            try {
                config(['database.connections.sqlsrv.database' => 'milacdb']);

                $this->assertSame(1, Artisan::call('mensajero:recuperar-publicacion', [
                    'evento' => $evento->id,
                    '--aplicar' => true,
                ]));
            } finally {
                config(['database.connections.sqlsrv.database' => $original]);
            }
        } finally {
            DB::rollBack();
        }
    }

    /**
     * @return array{0: ChatMonitoreado, 1: EventoCrudoWhatsapp}
     */
    private function crearEvento(): array
    {
        $sufijo = bin2hex(random_bytes(7));

        $conexion = ConexionWhatsapp::query()->create([
            'clave_conexion' => 'recuperacion-'.$sufijo,
            'proveedor' => 'recolector_whatsapp',
            'estado' => 'conectado',
        ]);

        $grupo = ChatMonitoreado::query()->create([
            'conexion_id' => $conexion->id,
            'id_chat_proveedor' => 'recuperacion-'.$sufijo.'@g.us',
            'tipo_chat' => 'grupo',
            'es_monitoreado' => true,
            'es_activo' => true,
        ]);

        return [$grupo, $this->crearEventoEnGrupo($grupo)];
    }

    private function crearEventoEnGrupo(ChatMonitoreado $grupo): EventoCrudoWhatsapp
    {
        $clave = 'recuperacion-'.bin2hex(random_bytes(8));

        $captura = app(CapturarEventoWhatsappEntrante::class)->ejecutar(new EventoWhatsappEntrante(
            conexionId: (int) $grupo->conexion_id,
            chatId: $grupo->id_chat_proveedor,
            tipoEvento: 'mensaje',
            tipoMensaje: 'texto',
            eventoExternoId: $clave,
            mensajeProveedorId: $clave,
            contenidoCrudo: json_encode(['message' => ['conversation' => 'Prueba temporal']], JSON_THROW_ON_ERROR),
            recibidoEn: CarbonImmutable::now('UTC'),
            versionContrato: 'v3-recolector',
        ));

        $this->assertSame('aceptado', $captura->estado);

        return EventoCrudoWhatsapp::query()->findOrFail($captura->eventoCrudoId);
    }
}
