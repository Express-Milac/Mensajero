<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mensajero_acontecimientos_operativos', function (Blueprint $tabla): void {
            $tabla->bigIncrements('id');
            $tabla->unsignedBigInteger('analisis_ia_id');
            $tabla->unsignedBigInteger('chat_monitoreado_id');
            $tabla->unsignedBigInteger('mensaje_objetivo_id');
            $tabla->string('categoria', 40);
            $tabla->string('titulo', 180);
            $tabla->text('resumen');
            $tabla->string('estado', 32)->default('pendiente_revision');
            $tabla->dateTime('creado_en', 6);
            $tabla->dateTime('actualizado_en', 6);

            $tabla->unique('analisis_ia_id', 'mensajero_acontecimiento_analisis_uq');
            $tabla->index(['chat_monitoreado_id', 'creado_en'], 'mensajero_acontecimiento_grupo_fecha');
            $tabla->foreign('analisis_ia_id', 'mensajero_acontecimiento_analisis_fk')
                ->references('id')->on('mensajero_analisis_operativos_ia')->onDelete('no action');
            $tabla->foreign('chat_monitoreado_id', 'mensajero_acontecimiento_grupo_fk')
                ->references('id')->on('mensajero_chats_monitoreados')->onDelete('no action');
            $tabla->foreign('mensaje_objetivo_id', 'mensajero_acontecimiento_objetivo_fk')
                ->references('id')->on('mensajero_mensajes_whatsapp')->onDelete('no action');
        });

        Schema::create('mensajero_evidencias_acontecimientos', function (Blueprint $tabla): void {
            $tabla->bigIncrements('id');
            $tabla->unsignedBigInteger('acontecimiento_id');
            $tabla->unsignedBigInteger('mensaje_whatsapp_id');
            $tabla->string('vinculo', 32); // sustento_ia | posible_adjunta
            $tabla->dateTime('creado_en', 6);

            $tabla->unique(['acontecimiento_id', 'mensaje_whatsapp_id'], 'mensajero_evidencia_acontecimiento_uq');
            $tabla->index('mensaje_whatsapp_id', 'mensajero_evidencia_mensaje_idx');
            $tabla->foreign('acontecimiento_id', 'mensajero_evidencia_acontecimiento_fk')
                ->references('id')->on('mensajero_acontecimientos_operativos')->onDelete('no action');
            $tabla->foreign('mensaje_whatsapp_id', 'mensajero_evidencia_mensaje_fk')
                ->references('id')->on('mensajero_mensajes_whatsapp')->onDelete('no action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensajero_evidencias_acontecimientos');
        Schema::dropIfExists('mensajero_acontecimientos_operativos');
    }
};
