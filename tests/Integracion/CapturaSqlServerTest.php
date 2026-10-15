<?php

namespace Tests\Integracion;

use App\Datos\WhatsApp\EventoEntranteWhatsapp;
use App\Enumeraciones\DisposicionEntrada;
use App\Modelos\ConexionWhatsapp;
use App\Modelos\ConversacionMonitoreada;
use App\Modelos\EventoCrudoWhatsapp;
use App\Modelos\MensajeBandejaSalida;
use App\Servicios\Captura\CapturarEventoEntranteWhatsapp;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\CasoPrueba;

class CapturaSqlServerTest extends CasoPrueba
{
    public function test_lista_blanca_adhesivos_e_idempotencia_antes_de_evidencia_cruda(): void
    {
        $this->assertStringEndsWith('_test', config('database.connections.sqlsrv.database'), 'Use una base desechable terminada en _test.');
        $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();

        DB::beginTransaction();
        try {
            $conexion = ConexionWhatsapp::create([
                'clave_conexion' => 'principal-pruebas',
                'proveedor' => 'web_autohospedado',
                'estado' => 'conectado',
            ]);

            $conversacion = ConversacionMonitoreada::create([
                'conexion_id' => $conexion->id,
                'id_conversacion_proveedor' => 'operacion-prueba@g.us',
                'tipo_conversacion' => 'grupo',
            ]);

            $captura = app(CapturarEventoEntranteWhatsapp::class);
            $evento = new EventoEntranteWhatsapp(
                conexionId: (int) $conexion->id,
                conversacionId: $conversacion->id_conversacion_proveedor,
                tipoEvento: 'mensaje',
                tipoMensaje: 'texto',
                eventoExternoId: 'evt-001',
                mensajeProveedorId: 'msg-001',
                contenidoCrudo: '{"id":"evt-001","texto":"Unidad lista"}',
                recibidoEn: CarbonImmutable::parse('2026-10-15T18:00:00Z'),
            );

            $ignorado = $captura->manejar($evento);
            $this->assertSame('ignorado', $ignorado->estado);
            $this->assertSame(DisposicionEntrada::IgnorarNoMonitoreada, $ignorado->disposicion);
            $this->assertSame(0, EventoCrudoWhatsapp::count());
            $this->assertSame(0, MensajeBandejaSalida::count());

            $conversacion->update(['esta_monitoreada' => true]);
            $aceptado = $captura->manejar($evento);
            $this->assertSame('aceptado', $aceptado->estado);
            $this->assertSame(1, EventoCrudoWhatsapp::count());
            $this->assertSame(1, MensajeBandejaSalida::count());

            $duplicado = $captura->manejar($evento);
            $this->assertSame('duplicado', $duplicado->estado);
            $this->assertSame($aceptado->eventoCrudoId, $duplicado->eventoCrudoId);
            $this->assertSame(1, EventoCrudoWhatsapp::count());
            $this->assertSame(1, MensajeBandejaSalida::count());

            $adhesivo = new EventoEntranteWhatsapp(
                conexionId: (int) $conexion->id,
                conversacionId: $conversacion->id_conversacion_proveedor,
                tipoEvento: 'mensaje',
                tipoMensaje: 'sticker',
                eventoExternoId: 'evt-002',
                mensajeProveedorId: 'msg-002',
                contenidoCrudo: '{"id":"evt-002","tipo":"sticker"}',
                recibidoEn: CarbonImmutable::parse('2026-10-15T18:01:00Z'),
            );

            $ignoradoAdhesivo = $captura->manejar($adhesivo);
            $this->assertSame(DisposicionEntrada::IgnorarAdhesivo, $ignoradoAdhesivo->disposicion);
            $this->assertSame(1, EventoCrudoWhatsapp::count());
            $this->assertSame(1, MensajeBandejaSalida::count());

            $conversacion->update(['esta_activa' => false]);
            $eventoTardio = new EventoEntranteWhatsapp(
                conexionId: (int) $conexion->id,
                conversacionId: $conversacion->id_conversacion_proveedor,
                tipoEvento: 'mensaje',
                tipoMensaje: 'texto',
                eventoExternoId: 'evt-003',
                mensajeProveedorId: 'msg-003',
                contenidoCrudo: '{"id":"evt-003","texto":"No debe entrar"}',
                recibidoEn: CarbonImmutable::parse('2026-10-15T18:02:00Z'),
            );
            $this->assertSame(DisposicionEntrada::IgnorarConversacionInactiva, $captura->manejar($eventoTardio)->disposicion);
            $this->assertSame(1, EventoCrudoWhatsapp::count());
        } finally {
            DB::rollBack();
        }
    }
}
