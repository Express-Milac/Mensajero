<?php

namespace Tests\Funcional;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class OperacionLotesGeminiTest extends TestCase
{
    public function test_bitacora_lee_dos_resultados_de_un_lote_sin_modificar_originales(): void
    {
        config([
            'mensajero.autenticacion.modo' => 'deshabilitado',
            'mensajero.consulta_local_sin_autenticacion' => true,
        ]);

        Schema::create('mensajero_chats_monitoreados', function (Blueprint $t): void {
            $t->id();
            $t->string('tipo_chat', 20);
            $t->boolean('es_activo');
            $t->boolean('es_monitoreado');
            $t->boolean('es_visible_tablero');
            $t->string('nombre_interno')->nullable();
            $t->string('nombre_grupo')->nullable();
        });
        Schema::create('mensajero_lotes_gemini', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('chat_monitoreado_id');
            $t->string('estado', 32);
        });
        Schema::create('mensajero_resultados_lote_gemini', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('lote_id');
            $t->string('estado', 32);
            $t->string('categoria', 40);
            $t->string('titulo');
            $t->text('resumen');
            $t->dateTime('creado_en');
        });
        Schema::create('mensajero_evidencias_resultado_lote_gemini', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('resultado_lote_id');
            $t->unsignedBigInteger('mensaje_whatsapp_id');
        });
        Schema::create('mensajero_mensajes_whatsapp', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('chat_monitoreado_id');
            $t->unsignedBigInteger('evento_crudo_id');
            $t->string('estado_captura', 24);
            $t->string('tipo_mensaje', 24);
            $t->text('pie_multimedia')->nullable();
        });

        DB::table('mensajero_chats_monitoreados')->insert([
            'id' => 10196, 'tipo_chat' => 'grupo', 'es_activo' => true,
            'es_monitoreado' => true, 'es_visible_tablero' => true,
            'nombre_interno' => 'Grupo de prueba', 'nombre_grupo' => 'Grupo de prueba',
        ]);
        DB::table('mensajero_lotes_gemini')->insert([
            'id' => 55, 'chat_monitoreado_id' => 10196, 'estado' => 'finalizado',
        ]);
        DB::table('mensajero_resultados_lote_gemini')->insert([
            [
                'id' => 56, 'lote_id' => 55, 'estado' => 'identificado_ia',
                'categoria' => 'logistica', 'titulo' => 'Llegada de unidad ficticia',
                'resumen' => 'Se reportó una llegada sin confirmación externa.',
                'creado_en' => '2026-10-26 14:00:00',
            ],
            [
                'id' => 57, 'lote_id' => 55, 'estado' => 'identificado_ia',
                'categoria' => 'incidencia', 'titulo' => 'Retraso de carga ficticia',
                'resumen' => 'Un participante mencionó una demora operativa.',
                'creado_en' => '2026-10-26 14:00:01',
            ],
        ]);
        DB::table('mensajero_mensajes_whatsapp')->insert([
            ['id' => 101, 'chat_monitoreado_id' => 10196,
                'evento_crudo_id' => 201, 'estado_captura' => 'normalizado',
                'tipo_mensaje' => 'texto'],
            ['id' => 102, 'chat_monitoreado_id' => 10196,
                'evento_crudo_id' => 202, 'estado_captura' => 'normalizado',
                'tipo_mensaje' => 'texto'],
        ]);
        DB::table('mensajero_evidencias_resultado_lote_gemini')->insert([
            ['resultado_lote_id' => 56, 'mensaje_whatsapp_id' => 101],
            ['resultado_lote_id' => 57, 'mensaje_whatsapp_id' => 102],
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->get('/operacion')
            ->assertOk()
            ->assertSee('Acontecimientos de conversaciones')
            ->assertSee('Llegada de unidad ficticia')
            ->assertSee('Retraso de carga ficticia')
            ->assertSee('Original #101')
            ->assertSee('Original #102');

        $this->assertSame(2, DB::table('mensajero_resultados_lote_gemini')->count());
        $this->assertSame(2, DB::table('mensajero_evidencias_resultado_lote_gemini')->count());
    }
}
