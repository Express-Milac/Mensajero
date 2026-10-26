<?php

namespace Tests\Funcional;

use App\Services\IA\PersistirResultadosLoteGemini;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class PersistirResultadosLoteGeminiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('Se requiere PDO SQLite para probar persistencia en memoria.');
        }

        // Esquema mínimo exclusivo de esta prueba, sin migraciones de MILAC.
        Schema::create('mensajero_lotes_gemini', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('chat_monitoreado_id');
            $t->string('estado', 32);
            $t->string('modelo', 90)->nullable();
            $t->unsignedInteger('tokens_entrada')->nullable();
            $t->unsignedInteger('tokens_salida')->nullable();
            $t->dateTime('finalizado_en')->nullable();
        });
        Schema::create('mensajero_mensajes_whatsapp', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('chat_monitoreado_id');
        });
        Schema::create('mensajero_mensajes_lote_gemini', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('lote_id');
            $t->unsignedBigInteger('mensaje_whatsapp_id')->unique();
            $t->unsignedTinyInteger('posicion');
            $t->unique(['lote_id', 'posicion']);
        });
        Schema::create('mensajero_resultados_lote_gemini', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('lote_id');
            $t->unsignedTinyInteger('posicion');
            $t->string('categoria', 40);
            $t->string('titulo', 180);
            $t->text('resumen');
            $t->string('estado', 32);
            $t->dateTime('creado_en');
            $t->unique(['lote_id', 'posicion']);
        });
        Schema::create('mensajero_evidencias_resultado_lote_gemini', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('resultado_lote_id');
            $t->unsignedBigInteger('mensaje_whatsapp_id');
            $t->unique(['resultado_lote_id', 'mensaje_whatsapp_id']);
        });

        DB::table('mensajero_lotes_gemini')->insert([
            'id' => 7, 'chat_monitoreado_id' => 10196, 'estado' => 'publicado',
        ]);
        DB::table('mensajero_mensajes_whatsapp')->insert([
            ['id' => 101, 'chat_monitoreado_id' => 10196],
            ['id' => 102, 'chat_monitoreado_id' => 10196],
            ['id' => 999, 'chat_monitoreado_id' => 10197],
        ]);
        DB::table('mensajero_mensajes_lote_gemini')->insert([
            ['lote_id' => 7, 'mensaje_whatsapp_id' => 101, 'posicion' => 1],
            ['lote_id' => 7, 'mensaje_whatsapp_id' => 102, 'posicion' => 2],
        ]);
    }

    private function respuesta(array $eventos): array
    {
        return [
            'modelo' => 'gemini-3.5-flash-lite',
            'tokens_entrada' => 300,
            'tokens_salida' => 120,
            'acontecimientos' => $eventos,
        ];
    }

    private function evento(string $titulo, array $ids): array
    {
        return [
            'categoria' => 'logistica',
            'titulo' => $titulo,
            'resumen' => 'El operador reportó una actualización sin comprobación independiente.',
            'mensaje_ids' => $ids,
        ];
    }

    public function test_persiste_dos_eventos_y_segunda_ejecucion_no_duplica(): void
    {
        $servicio = app(PersistirResultadosLoteGemini::class);
        $respuesta = $this->respuesta([
            $this->evento('Llegada al patio', [101]),
            $this->evento('Carga pendiente', [102]),
        ]);

        $this->assertSame('finalizado', $servicio->guardar(7, $respuesta));
        $this->assertSame('ya_finalizado', $servicio->guardar(7, $respuesta));

        $this->assertSame(2, DB::table('mensajero_resultados_lote_gemini')->count());
        $this->assertSame(2, DB::table('mensajero_evidencias_resultado_lote_gemini')->count());
        $this->assertSame('finalizado', DB::table('mensajero_lotes_gemini')->where('id', 7)->value('estado'));
        $this->assertSame('gemini-3.5-flash-lite', DB::table('mensajero_lotes_gemini')->where('id', 7)->value('modelo'));
    }

    public function test_lote_sin_acontecimientos_queda_finalizado(): void
    {
        $this->assertSame('finalizado', app(PersistirResultadosLoteGemini::class)->guardar(7, $this->respuesta([])));
        $this->assertSame(0, DB::table('mensajero_resultados_lote_gemini')->count());
        $this->assertSame('finalizado', DB::table('mensajero_lotes_gemini')->where('id', 7)->value('estado'));
    }

    public function test_rechaza_fuente_ajena_y_revierte_toda_la_transaccion(): void
    {
        try {
            app(PersistirResultadosLoteGemini::class)->guardar(7, $this->respuesta([
                $this->evento('Llegada al patio', [101]),
                $this->evento('Incidencia externa', [999]),
            ]));
            $this->fail('El ID fuera del lote debe rechazarse.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('ajenos', $e->getMessage());
        }

        $this->assertSame(0, DB::table('mensajero_resultados_lote_gemini')->count());
        $this->assertSame('publicado', DB::table('mensajero_lotes_gemini')->where('id', 7)->value('estado'));
    }

    public function test_rechaza_guardar_un_lote_que_aun_no_fue_publicado(): void
    {
        DB::table('mensajero_lotes_gemini')->where('id', 7)->update(['estado' => 'reservado']);

        $this->expectException(DomainException::class);
        app(PersistirResultadosLoteGemini::class)->guardar(7, $this->respuesta([
            $this->evento('Llegada al patio', [101]),
        ]));
    }

    public function test_rechaza_asociacion_de_mensaje_de_otro_grupo(): void
    {
        DB::table('mensajero_mensajes_lote_gemini')->where('mensaje_whatsapp_id', 102)
            ->update(['mensaje_whatsapp_id' => 999]);
        $this->expectException(DomainException::class);
        app(PersistirResultadosLoteGemini::class)->guardar(7, $this->respuesta([
            $this->evento('Incidencia externa', [999]),
        ]));
    }
}
