<?php

namespace Tests\Carga;

use App\Models\ChatMonitoreado;
use App\Models\ConexionWhatsapp;
use App\Models\EventoCrudoWhatsapp;
use App\Services\Monitoreo\CambiarMonitoreoChat;
use App\Services\Monitoreo\SincronizarChatsDescubiertos;
use App\Services\Seguridad\ValidadorFirmaPasarela;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CargaPasarelaSqlServerTest extends TestCase
{
    private const TOTAL_EVENTOS = 1000;

    private const TOTAL_REPETICIONES = 50;

    private const TOTAL_GRUPOS = 4;

    private const TOTAL_REMITENTES = 25;

    private string $secreto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->secreto = str_repeat('c', 48);
        config([
            'mensajero.pasarela_secreto' => $this->secreto,
            'mensajero.seguridad_pasarela.ventana_segundos' => 300,
        ]);
    }

    public function test_mil_eventos_se_conservan_sin_perdida_y_los_reintentos_se_deduplican(): void
    {
        $this->assertSame('sqlsrv', config('database.default'));
        $this->assertStringEndsWith('_test', (string) config('database.connections.sqlsrv.database'));
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();

        DB::beginTransaction();

        try {
            [$conexion, $chats, $sufijo] = $this->crearEntornoCarga();
            $muestrasRepeticion = [];
            $inicio = hrtime(true);

            for ($indice = 0; $indice < self::TOTAL_EVENTOS; $indice++) {
                $chat = $chats[$indice % self::TOTAL_GRUPOS];
                $numeroEvento = $indice + 1;
                $idEvento = sprintf('CARGA-%s-%06d', $sufijo, $numeroEvento);
                $remitente = sprintf(
                    'remitente-carga-%02d@s.whatsapp.net',
                    ($indice % self::TOTAL_REMITENTES) + 1,
                );
                $evento = $this->eventoCarga(
                    $conexion,
                    $chat,
                    $idEvento,
                    $remitente,
                    $numeroEvento,
                );

                $respuesta = $this->enviarFirmado(
                    '/api/pasarela/v1/eventos',
                    $evento,
                    sprintf('carga-%s-evento-%06d', $sufijo, $numeroEvento),
                );

                $respuesta->assertOk()->assertJsonPath('estado', 'aceptado');

                if ($indice < self::TOTAL_REPETICIONES) {
                    $muestrasRepeticion[] = $evento;
                }
            }

            $finEventos = hrtime(true);

            foreach ($muestrasRepeticion as $indice => $evento) {
                $respuesta = $this->enviarFirmado(
                    '/api/pasarela/v1/eventos',
                    $evento,
                    sprintf('carga-%s-repeticion-%03d', $sufijo, $indice + 1),
                );

                $respuesta->assertOk()->assertJsonPath('estado', 'duplicado');
            }

            $finTotal = hrtime(true);
            $consulta = EventoCrudoWhatsapp::query()->where('conexion_id', $conexion->id);

            $this->assertSame(self::TOTAL_EVENTOS, (clone $consulta)->count());
            $this->assertSame(
                self::TOTAL_EVENTOS,
                (clone $consulta)->distinct()->count('id_evento_externo'),
            );
            $this->assertSame(
                self::TOTAL_REMITENTES,
                (clone $consulta)->distinct()->count('id_remitente_proveedor'),
            );
            $this->assertSame(0, (clone $consulta)->whereNull('id_evento_externo')->count());
            $this->assertSame(0, (clone $consulta)->whereNull('id_remitente_proveedor')->count());
            $this->assertSame(
                self::TOTAL_EVENTOS,
                (clone $consulta)->where('tipo_evento', 'mensaje')->where('tipo_mensaje', 'texto')->count(),
            );
            $this->assertSame(
                self::TOTAL_EVENTOS,
                (clone $consulta)->where('version_contrato', 'v3-recolector')->count(),
            );

            foreach ($chats as $chat) {
                $consultaChat = EventoCrudoWhatsapp::query()
                    ->where('conexion_id', $conexion->id)
                    ->where('chat_monitoreado_id', $chat->id);

                $this->assertSame(250, (clone $consultaChat)->count());
                $this->assertSame(
                    self::TOTAL_REMITENTES,
                    (clone $consultaChat)->distinct()->count('id_remitente_proveedor'),
                );
            }

            $eventosIds = EventoCrudoWhatsapp::query()
                ->where('conexion_id', $conexion->id)
                ->pluck('id');
            $buzon = DB::table('mensajero_buzon_salida')
                ->where('tema', 'whatsapp.crudo.aceptado')
                ->where('tipo_entidad', 'evento_crudo_whatsapp')
                ->whereIn('entidad_id', $eventosIds)
                ->count();
            $this->assertSame(self::TOTAL_EVENTOS, $buzon);

            $solicitudesPasarela = DB::table('mensajero_solicitudes_pasarela')
                ->where('identificador_solicitud', 'like', 'carga-'.$sufijo.'-%')
                ->count();
            $this->assertSame(
                self::TOTAL_EVENTOS + self::TOTAL_REPETICIONES,
                $solicitudesPasarela,
            );

            $segundosEventos = ($finEventos - $inicio) / 1_000_000_000;
            $segundosTotal = ($finTotal - $inicio) / 1_000_000_000;
            $eventosPorSegundo = self::TOTAL_EVENTOS / max($segundosEventos, 0.000001);

            fwrite(STDERR, sprintf(
                "\n[Mensajero carga] eventos=%d; grupos=%d; remitentes=%d; repetidos=%d; segundos_eventos=%.3f; segundos_total=%.3f; eventos_por_segundo=%.2f\n",
                self::TOTAL_EVENTOS,
                self::TOTAL_GRUPOS,
                self::TOTAL_REMITENTES,
                self::TOTAL_REPETICIONES,
                $segundosEventos,
                $segundosTotal,
                $eventosPorSegundo,
            ));
        } finally {
            DB::rollBack();
        }
    }

    /**
     * @return array{0: ConexionWhatsapp, 1: array<int, ChatMonitoreado>, 2: string}
     */
    private function crearEntornoCarga(): array
    {
        $sufijo = bin2hex(random_bytes(6));
        $conexion = ConexionWhatsapp::query()->create([
            'clave_conexion' => 'carga-'.$sufijo,
            'proveedor' => 'recolector_whatsapp',
            'estado' => 'conectado',
        ]);

        $descubiertos = [];

        for ($indice = 1; $indice <= self::TOTAL_GRUPOS; $indice++) {
            $descubiertos[] = [
                'id_chat_proveedor' => sprintf('grupo-carga-%02d-%s@g.us', $indice, $sufijo),
                'tipo_chat' => 'grupo',
                'nombre_grupo' => 'Grupo carga '.$indice,
                'es_activo' => true,
            ];
        }

        app(SincronizarChatsDescubiertos::class)->ejecutar($conexion, $descubiertos);

        /** @var array<int, ChatMonitoreado> $chats */
        $chats = ChatMonitoreado::query()
            ->where('conexion_id', $conexion->id)
            ->orderBy('id')
            ->get()
            ->all();

        $this->assertCount(self::TOTAL_GRUPOS, $chats);

        foreach ($chats as $chat) {
            app(CambiarMonitoreoChat::class)->ejecutar(
                (int) $chat->id,
                true,
                'prueba_carga',
                null,
                'Autorizar grupo sintético para prueba de carga',
            );
        }

        return [$conexion, $chats, $sufijo];
    }

    private function eventoCarga(
        ConexionWhatsapp $conexion,
        ChatMonitoreado $chat,
        string $idEvento,
        string $remitente,
        int $numeroEvento,
    ): array {
        return [
            'clave_conexion' => $conexion->clave_conexion,
            'chat_id' => $chat->id_chat_proveedor,
            'tipo_evento' => 'mensaje',
            'tipo_mensaje' => 'texto',
            'evento_externo_id' => $idEvento,
            'mensaje_proveedor_id' => $idEvento,
            'id_remitente_proveedor' => $remitente,
            'id_mensaje_citado_proveedor' => null,
            'id_mensaje_objetivo_proveedor' => null,
            'contenido_crudo' => json_encode(
                ['conversation' => sprintf('mensaje carga %06d', $numeroEvento)],
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            ),
            'recibido_en' => now('UTC')->toIso8601String(),
            'version_contrato' => 'v3-recolector',
            'multimedia' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $contenido
     */
    private function enviarFirmado(string $ruta, array $contenido, string $identificador): TestResponse
    {
        $cuerpo = json_encode(
            $contenido,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
        $marcaTiempo = (string) now()->getTimestamp();
        $firma = app(ValidadorFirmaPasarela::class)->generar(
            $identificador,
            $marcaTiempo,
            $cuerpo,
            $this->secreto,
        );

        return $this->call('POST', $ruta, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_MENSAJERO_ID' => $identificador,
            'HTTP_X_MENSAJERO_MARCA_TIEMPO' => $marcaTiempo,
            'HTTP_X_MENSAJERO_FIRMA' => $firma,
        ], $cuerpo);
    }
}
