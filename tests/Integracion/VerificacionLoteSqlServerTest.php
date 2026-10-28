<?php

namespace Tests\Integracion;

use App\Data\WhatsApp\EventoWhatsappEntrante;
use App\Models\ChatMonitoreado;
use App\Models\ConexionWhatsapp;
use App\Models\EventoCrudoWhatsapp;
use App\Models\MensajeWhatsapp;
use App\Services\Capture\CapturarEventoWhatsappEntrante;
use App\Services\Normalizacion\NormalizarEventoWhatsapp;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VerificacionLoteSqlServerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('sqlsrv', config('database.default'));
        $this->assertStringEndsWith('_test', (string) config('database.connections.sqlsrv.database'));
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();

        // Estas pruebas necesitan analizar la salida REAL de Artisan::call.
        // Laravel 8 conserva un OutputStyle simulado tras $this->artisan(),
        // lo cual deja Artisan::output() vacio aunque se hayan escrito lineas.
        $this->withoutMockingConsoleOutput();
    }

    public function test_verifica_dos_mensajes_y_su_cita_sin_escribir(): void
    {
        DB::beginTransaction();

        try {
            $grupo = $this->crearGrupo();
            $original = $this->crearEvento($grupo, 'Mensaje original');
            $cita = $this->crearEvento($grupo, 'Respuesta citada', $original->id_evento_externo);

            $normalizador = app(NormalizarEventoWhatsapp::class);
            $this->assertSame('normalizado', $normalizador->ejecutar((int) $original->id));
            $this->assertSame('normalizado', $normalizador->ejecutar((int) $cita->id));

            $mensajeCitado = MensajeWhatsapp::query()->where('evento_crudo_id', $cita->id)->firstOrFail();
            $mensajeOriginal = MensajeWhatsapp::query()->where('evento_crudo_id', $original->id)->firstOrFail();
            $this->assertSame((int) $mensajeOriginal->id, (int) $mensajeCitado->mensaje_citado_id);

            $ids = [$original->id, $cita->id];
            $eventosAntes = DB::table('mensajero_eventos_crudos_whatsapp')
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->get();
            $mensajesAntes = DB::table('mensajero_mensajes_whatsapp')
                ->whereIn('evento_crudo_id', $ids)
                ->orderBy('evento_crudo_id')
                ->get();

            $codigo = Artisan::call('mensajero:verificar-lote', [
                'grupo' => $grupo->id,
                '--ids' => $original->id.','.$cita->id,
            ]);

            // La salida de comandos con $this->line() puede estar vacía en
            // Artisan::output() durante PHPUnit pese a que el comando funciona.
            // Verificamos su resultado real y que la auditoría no escriba datos.
            $this->assertSame(0, $codigo, 'La auditoría del lote debe aprobar dos mensajes y su cita.');
            $this->assertEquals($eventosAntes, DB::table('mensajero_eventos_crudos_whatsapp')
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->get(), 'La auditoría no debe modificar los eventos crudos.');
            $this->assertEquals($mensajesAntes, DB::table('mensajero_mensajes_whatsapp')
                ->whereIn('evento_crudo_id', $ids)
                ->orderBy('evento_crudo_id')
                ->get(), 'La auditoría no debe modificar los mensajes normalizados.');
            $this->assertSame(2, MensajeWhatsapp::query()
                ->whereIn('evento_crudo_id', $ids)
                ->count());

            MensajeWhatsapp::query()
                ->where('evento_crudo_id', $cita->id)
                ->update(['mensaje_citado_id' => null]);

            $this->assertSame(1, Artisan::call('mensajero:verificar-lote', [
                'grupo' => $grupo->id,
                '--ids' => $original->id.','.$cita->id,
            ]));
            $this->assertStringContainsString('vínculo con mensaje citado incorrecto', Artisan::output());
        } finally {
            DB::rollBack();
        }
    }

    public function test_rechaza_hash_alterado_y_evento_no_normalizado(): void
    {
        DB::beginTransaction();

        try {
            $grupo = $this->crearGrupo();
            $evento = $this->crearEvento($grupo, 'Integridad de prueba');
            $argumentos = ['grupo' => $grupo->id, '--ids' => (string) $evento->id];

            $this->assertSame(1, Artisan::call('mensajero:verificar-lote', $argumentos));
            $this->assertStringContainsString('evento no normalizado', Artisan::output());

            $this->assertSame('normalizado', app(NormalizarEventoWhatsapp::class)->ejecutar((int) $evento->id));

            EventoCrudoWhatsapp::query()->whereKey($evento->id)
                ->update(['hash_contenido_sha256' => str_repeat('0', 64)]);

            $this->assertSame(1, Artisan::call('mensajero:verificar-lote', $argumentos));
            $this->assertStringContainsString('integridad SHA-256 incorrecta', Artisan::output());
        } finally {
            DB::rollBack();
        }
    }

    public function test_rechaza_ids_repetidos_y_base_de_produccion(): void
    {
        $this->assertSame(1, Artisan::call('mensajero:verificar-lote', [
            'grupo' => 10020,
            '--ids' => '1,1',
        ]));

        $original = config('database.connections.sqlsrv.database');

        try {
            config(['database.connections.sqlsrv.database' => 'milacdb']);

            $this->assertSame(1, Artisan::call('mensajero:verificar-lote', [
                'grupo' => 10020,
                '--ids' => '1',
            ]));
        } finally {
            config(['database.connections.sqlsrv.database' => $original]);
        }
    }

    private function crearGrupo(): ChatMonitoreado
    {
        $sufijo = bin2hex(random_bytes(7));
        $conexion = ConexionWhatsapp::query()->create([
            'clave_conexion' => 'verificacion-'.$sufijo,
            'proveedor' => 'recolector_whatsapp',
            'estado' => 'conectado',
        ]);

        return ChatMonitoreado::query()->create([
            'conexion_id' => $conexion->id,
            'id_chat_proveedor' => 'verificacion-'.$sufijo.'@g.us',
            'tipo_chat' => 'grupo',
            'es_monitoreado' => true,
            'es_activo' => true,
        ]);
    }

    private function crearEvento(
        ChatMonitoreado $grupo,
        string $texto,
        ?string $idCitado = null
    ): EventoCrudoWhatsapp {
        $clave = 'verificacion-'.bin2hex(random_bytes(8));

        $entrada = new EventoWhatsappEntrante(
            (int) $grupo->conexion_id,
            $grupo->id_chat_proveedor,
            'mensaje',
            'texto',
            $clave,
            $clave,
            json_encode([
                'message' => ['extendedTextMessage' => ['text' => $texto]],
            ], JSON_THROW_ON_ERROR),
            CarbonImmutable::now('UTC'),
            'v3-recolector',
            null,
            null,
            $idCitado
        );

        $resultado = app(CapturarEventoWhatsappEntrante::class)->ejecutar($entrada);
        $this->assertSame('aceptado', $resultado->estado);

        return EventoCrudoWhatsapp::query()->findOrFail($resultado->eventoCrudoId);
    }
}
