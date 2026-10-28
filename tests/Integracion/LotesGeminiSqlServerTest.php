<?php

namespace Tests\Integracion;

use App\Data\WhatsApp\EventoWhatsappEntrante;
use App\Models\ChatMonitoreado;
use App\Models\ConexionWhatsapp;
use App\Models\MensajeWhatsapp;
use App\Services\Capture\CapturarEventoWhatsappEntrante;
use App\Services\IA\PersistirResultadosLoteGemini;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Integra el esquema real de lotes con SQL Server, sin activar Gemini ni Redis.
 * Requiere exclusivamente mensajero_integracion_test con migraciones aplicadas.
 */
final class LotesGeminiSqlServerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->assertTrue(app()->environment('testing'));
        $this->assertSame('sqlsrv', config('database.default'));
        $this->assertSame('mensajero_integracion_test', config('database.connections.sqlsrv.database'));
        $this->assertSame(
            'mensajero_integracion_test',
            DB::selectOne('SELECT DB_NAME() AS nombre')->nombre
        );

        foreach ([
            'mensajero_lotes_gemini',
            'mensajero_mensajes_lote_gemini',
            'mensajero_resultados_lote_gemini',
            'mensajero_evidencias_resultado_lote_gemini',
        ] as $tabla) {
            $this->assertTrue(Schema::hasTable($tabla), 'Falta tabla: '.$tabla);
        }

        config()->set('mensajero.ia.lotes_habilitados', false);
        Http::preventStrayRequests();
    }

    public function test_sql_server_tiene_claves_foraneas_y_unicidades_de_lotes(): void
    {
        $foraneas = [
            'mensajero_lote_ia_grupo_fk',
            'mensajero_lote_ia_mensaje_lote_fk',
            'mensajero_lote_ia_mensaje_original_fk',
            'mensajero_lote_resultado_lote_fk',
            'mensajero_lote_resultado_evidencia_resultado_fk',
            'mensajero_lote_resultado_evidencia_mensaje_fk',
        ];
        $presentes = DB::table('sys.foreign_keys')->whereIn('name', $foraneas)->pluck('name')->all();
        $this->assertEqualsCanonicalizing($foraneas, $presentes);

        $indices = [
            'mensajero_lote_ia_grupo_hash_uq',
            'mensajero_lote_ia_mensaje_unico',
            'mensajero_lote_ia_posicion_unica',
            'mensajero_lote_resultado_posicion_uq',
            'mensajero_lote_resultado_evidencia_uq',
        ];
        $presentes = DB::table('sys.indexes')
            ->where('is_unique', 1)
            ->whereIn('name', $indices)
            ->pluck('name')
            ->all();
        $this->assertEqualsCanonicalizing($indices, $presentes);
        Http::assertNothingSent();
    }

    public function test_resultados_de_dos_mensajes_son_idempotentes_en_sql_server(): void
    {
        DB::beginTransaction();
        try {
            $grupo = $this->crearGrupo();
            $ids = $this->crearMensajes($grupo, 2);
            $loteId = $this->crearLote($grupo, $ids);

            $respuesta = [
                'modelo' => 'gemini-3.5-flash-lite',
                'tokens_entrada' => 300,
                'tokens_salida' => 120,
                'acontecimientos' => [
                    [
                        'categoria' => 'logistica',
                        'titulo' => 'Llegada al patio',
                        'resumen' => 'El operador reportó una llegada sin comprobación independiente.',
                        'mensaje_ids' => [$ids[0]],
                    ],
                    [
                        'categoria' => 'incidencia',
                        'titulo' => 'Carga pendiente',
                        'resumen' => 'Se reportó una carga pendiente de validación operativa.',
                        'mensaje_ids' => [$ids[1]],
                    ],
                ],
            ];

            $servicio = app(PersistirResultadosLoteGemini::class);
            $this->assertSame('finalizado', $servicio->guardar($loteId, $respuesta));
            $this->assertSame('ya_finalizado', $servicio->guardar($loteId, $respuesta));

            $this->assertSame(2, DB::table('mensajero_resultados_lote_gemini')
                ->where('lote_id', $loteId)->count());
            $this->assertSame(2, DB::table('mensajero_evidencias_resultado_lote_gemini AS e')
                ->join('mensajero_resultados_lote_gemini AS r', 'r.id', '=', 'e.resultado_lote_id')
                ->where('r.lote_id', $loteId)->count());
            $this->assertSame('finalizado', DB::table('mensajero_lotes_gemini')
                ->where('id', $loteId)->value('estado'));
            $this->assertSame('gemini-3.5-flash-lite', DB::table('mensajero_lotes_gemini')
                ->where('id', $loteId)->value('modelo'));
            Http::assertNothingSent();
        } finally {
            DB::rollBack();
        }
    }

    public function test_rechaza_asociacion_con_mensaje_de_otro_grupo_sin_persistir(): void
    {
        DB::beginTransaction();
        try {
            $grupo = $this->crearGrupo();
            $otroGrupo = $this->crearGrupo();
            $mensaje = $this->crearMensajes($otroGrupo, 1)[0];

            $loteId = $this->crearLote($grupo, [$mensaje]);
            $respuesta = [
                'modelo' => 'gemini-3.5-flash-lite',
                'tokens_entrada' => 12,
                'tokens_salida' => 3,
                'acontecimientos' => [],
            ];

            try {
                app(PersistirResultadosLoteGemini::class)->guardar($loteId, $respuesta);
                $this->fail('El lote con mensajes de otro grupo debe rechazarse.');
            } catch (DomainException $error) {
                $this->assertStringContainsString('otro grupo', $error->getMessage());
            }

            $this->assertSame('procesando', DB::table('mensajero_lotes_gemini')
                ->where('id', $loteId)->value('estado'));
            $this->assertSame(0, DB::table('mensajero_resultados_lote_gemini')
                ->where('lote_id', $loteId)->count());
            Http::assertNothingSent();
        } finally {
            DB::rollBack();
        }
    }

    private function crearGrupo(): ChatMonitoreado
    {
        $clave = bin2hex(random_bytes(7));
        $conexion = ConexionWhatsapp::query()->create([
            'clave_conexion' => 'sql-lotes-'.$clave,
            'proveedor' => 'recolector_whatsapp',
            'estado' => 'conectado',
        ]);

        return ChatMonitoreado::query()->create([
            'conexion_id' => $conexion->id,
            'id_chat_proveedor' => 'sql-lotes-'.$clave.'@g.us',
            'tipo_chat' => 'grupo',
            'es_monitoreado' => true,
            'es_visible_tablero' => true,
            'es_activo' => true,
        ]);
    }

    /** @return array<int,int> */
    private function crearMensajes(ChatMonitoreado $grupo, int $cantidad): array
    {
        $eventos = [];
        for ($i = 0; $i < $cantidad; $i++) {
            $clave = 'sql-lotes-'.bin2hex(random_bytes(8));
            $evento = new EventoWhatsappEntrante(
                conexionId: (int) $grupo->conexion_id,
                chatId: $grupo->id_chat_proveedor,
                tipoEvento: 'mensaje',
                tipoMensaje: 'texto',
                eventoExternoId: $clave,
                mensajeProveedorId: $clave,
                contenidoCrudo: json_encode([
                    'message' => ['conversation' => 'Texto sintético de integración SQL Server'],
                ], JSON_THROW_ON_ERROR),
                recibidoEn: CarbonImmutable::now('UTC'),
                versionContrato: 'v3-recolector',
            );

            $resultado = app(CapturarEventoWhatsappEntrante::class)->ejecutar($evento);
            $this->assertSame('aceptado', $resultado->estado);
            $eventos[] = (int) $resultado->eventoCrudoId;
        }

        $this->assertSame(0, Artisan::call('mensajero:normalizar-lote', [
            'grupo' => $grupo->id,
            '--ids' => implode(',', $eventos),
            '--limite' => $cantidad,
            '--aplicar' => true,
        ]));

        $ids = MensajeWhatsapp::query()
            ->whereIn('evento_crudo_id', $eventos)
            ->orderBy('evento_crudo_id')->pluck('id')
            ->map(static fn ($id): int => (int) $id)->all();
        $this->assertCount($cantidad, $ids);

        return $ids;
    }

    /** @param array<int,int> $ids */
    private function crearLote(ChatMonitoreado $grupo, array $ids): int
    {
        $loteId = (int) DB::table('mensajero_lotes_gemini')->insertGetId([
            'chat_monitoreado_id' => $grupo->id,
            'hash_mensajes' => hash('sha256', bin2hex(random_bytes(16))),
            'estado' => 'procesando',
            'reservado_en' => now('UTC')->format('Y-m-d H:i:s.u'),
        ]);

        foreach ($ids as $posicion => $mensajeId) {
            DB::table('mensajero_mensajes_lote_gemini')->insert([
                'lote_id' => $loteId,
                'mensaje_whatsapp_id' => $mensajeId,
                'posicion' => $posicion + 1,
            ]);
        }

        return $loteId;
    }
}
