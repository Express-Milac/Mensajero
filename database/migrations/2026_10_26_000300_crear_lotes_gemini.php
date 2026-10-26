<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estructura INACTIVA mientras no exista un despachador de lotes autorizado.
 * La migración no modifica las tablas vigentes del piloto de mensaje único.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mensajero_lotes_gemini', function (Blueprint $tabla): void {
            $tabla->bigIncrements('id');
            $tabla->unsignedBigInteger('chat_monitoreado_id');
            $tabla->string('hash_mensajes', 64);
            $tabla->string('estado', 32); // reservado | publicado | finalizado | requiere_revision
            $tabla->string('origen', 24)->default('planificacion');
            $tabla->dateTime('reservado_en', 6);
            $tabla->dateTime('publicado_en', 6)->nullable();
            $tabla->dateTime('finalizado_en', 6)->nullable();
            $tabla->unsignedInteger('tokens_entrada')->nullable();
            $tabla->unsignedInteger('tokens_salida')->nullable();
            $tabla->string('error_tipo', 120)->nullable();

            $tabla->unique(['chat_monitoreado_id', 'hash_mensajes'], 'mensajero_lote_ia_grupo_hash_uq');
            $tabla->index(['chat_monitoreado_id', 'reservado_en'], 'mensajero_lote_ia_grupo_fecha_idx');
            $tabla->foreign('chat_monitoreado_id', 'mensajero_lote_ia_grupo_fk')
                ->references('id')->on('mensajero_chats_monitoreados')->onDelete('no action');
        });

        Schema::create('mensajero_mensajes_lote_gemini', function (Blueprint $tabla): void {
            $tabla->bigIncrements('id');
            $tabla->unsignedBigInteger('lote_id');
            $tabla->unsignedBigInteger('mensaje_whatsapp_id');
            $tabla->unsignedTinyInteger('posicion');

            // Una fuente solo puede pertenecer a un lote enviado, nunca dos.
            $tabla->unique('mensaje_whatsapp_id', 'mensajero_lote_ia_mensaje_unico');
            $tabla->unique(['lote_id', 'posicion'], 'mensajero_lote_ia_posicion_unica');
            $tabla->foreign('lote_id', 'mensajero_lote_ia_mensaje_lote_fk')
                ->references('id')->on('mensajero_lotes_gemini')->onDelete('no action');
            $tabla->foreign('mensaje_whatsapp_id', 'mensajero_lote_ia_mensaje_original_fk')
                ->references('id')->on('mensajero_mensajes_whatsapp')->onDelete('no action');
        });

        Schema::create('mensajero_resultados_lote_gemini', function (Blueprint $tabla): void {
            $tabla->bigIncrements('id');
            $tabla->unsignedBigInteger('lote_id');
            $tabla->unsignedTinyInteger('posicion');
            $tabla->string('categoria', 40);
            $tabla->string('titulo', 180);
            $tabla->text('resumen');
            $tabla->string('estado', 32)->default('identificado_ia');
            $tabla->dateTime('creado_en', 6);

            $tabla->unique(['lote_id', 'posicion'], 'mensajero_lote_resultado_posicion_uq');
            $tabla->foreign('lote_id', 'mensajero_lote_resultado_lote_fk')
                ->references('id')->on('mensajero_lotes_gemini')->onDelete('no action');
        });

        Schema::create('mensajero_evidencias_resultado_lote_gemini', function (Blueprint $tabla): void {
            $tabla->bigIncrements('id');
            $tabla->unsignedBigInteger('resultado_lote_id');
            $tabla->unsignedBigInteger('mensaje_whatsapp_id');

            $tabla->unique(
                ['resultado_lote_id', 'mensaje_whatsapp_id'],
                'mensajero_lote_resultado_evidencia_uq'
            );
            $tabla->foreign('resultado_lote_id', 'mensajero_lote_resultado_evidencia_resultado_fk')
                ->references('id')->on('mensajero_resultados_lote_gemini')->onDelete('no action');
            $tabla->foreign('mensaje_whatsapp_id', 'mensajero_lote_resultado_evidencia_mensaje_fk')
                ->references('id')->on('mensajero_mensajes_whatsapp')->onDelete('no action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensajero_evidencias_resultado_lote_gemini');
        Schema::dropIfExists('mensajero_resultados_lote_gemini');
        Schema::dropIfExists('mensajero_mensajes_lote_gemini');
        Schema::dropIfExists('mensajero_lotes_gemini');
    }
};
