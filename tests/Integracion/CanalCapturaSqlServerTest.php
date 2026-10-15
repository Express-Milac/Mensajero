<?php

namespace Tests\Integracion;

use App\Data\WhatsApp\EventoWhatsappEntrante;
use App\Enums\DisposicionEntrada;
use App\Models\ChatMonitoreado;
use App\Models\ConexionWhatsapp;
use App\Models\EventoCrudoWhatsapp;
use App\Models\MensajeBuzonSalida;
use App\Services\Capture\CapturarEventoWhatsappEntrante;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CanalCapturaSqlServerTest extends TestCase
{
    public function test_lista_permitida_pegatina_e_idempotencia_antes_de_crudo(): void
    {
        $this->assertStringEndsWith('_test', config('database.connections.sqlsrv.database'), 'Use una base desechable cuyo nombre termine en _test.');
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();

        DB::beginTransaction();
        try {
            $conexion = ConexionWhatsapp::create(['clave_conexion' => 'canal-principal', 'proveedor' => 'web_autohospedado', 'estado' => 'conectado']);
            $chat = ChatMonitoreado::create(['conexion_id' => $conexion->id, 'id_chat_proveedor' => 'operacion-prueba@g.us', 'tipo_chat' => 'grupo']);
            $captura = app(CapturarEventoWhatsappEntrante::class);
            $evento = new EventoWhatsappEntrante(
                conexionId: (int) $conexion->id,
                chatId: $chat->id_chat_proveedor,
                tipoEvento: 'mensaje',
                tipoMensaje: 'texto',
                eventoExternoId: 'evt-001',
                mensajeProveedorId: 'msg-001',
                contenidoCrudo: '{"id":"evt-001","texto":"Unidad lista"}',
                recibidoEn: CarbonImmutable::parse('2026-10-15T18:00:00Z'),
            );

            $ignorado = $captura->ejecutar($evento);
            $this->assertSame('ignorado', $ignorado->estado);
            $this->assertSame(DisposicionEntrada::IgnorarNoMonitoreado, $ignorado->disposicion);
            $this->assertSame(0, EventoCrudoWhatsapp::count());
            $this->assertSame(0, MensajeBuzonSalida::count());

            $chat->update(['es_monitoreado' => true]);
            $aceptado = $captura->ejecutar($evento);
            $this->assertSame('aceptado', $aceptado->estado);
            $this->assertSame(1, EventoCrudoWhatsapp::count());
            $this->assertSame(1, MensajeBuzonSalida::count());

            $duplicado = $captura->ejecutar($evento);
            $this->assertSame('duplicado', $duplicado->estado);
            $this->assertSame($aceptado->eventoCrudoId, $duplicado->eventoCrudoId);
            $this->assertSame(1, EventoCrudoWhatsapp::count());
            $this->assertSame(1, MensajeBuzonSalida::count());

            $pegatina = new EventoWhatsappEntrante(
                conexionId: (int) $conexion->id,
                chatId: $chat->id_chat_proveedor,
                tipoEvento: 'mensaje',
                tipoMensaje: 'sticker',
                eventoExternoId: 'evt-002',
                mensajeProveedorId: 'msg-002',
                contenidoCrudo: '{"id":"evt-002","tipo":"sticker"}',
                recibidoEn: CarbonImmutable::parse('2026-10-15T18:01:00Z'),
            );
            $ignoradoPegatina = $captura->ejecutar($pegatina);
            $this->assertSame(DisposicionEntrada::IgnorarPegatina, $ignoradoPegatina->disposicion);
            $this->assertSame(1, EventoCrudoWhatsapp::count());
            $this->assertSame(1, MensajeBuzonSalida::count());
        } finally {
            DB::rollBack();
        }
    }
}
