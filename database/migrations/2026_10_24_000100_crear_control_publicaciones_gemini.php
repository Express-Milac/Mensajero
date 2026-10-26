<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mensajero_control_publicaciones_gemini', function (Blueprint $tabla): void {
            $tabla->bigIncrements('id');
            $tabla->unsignedBigInteger('mensaje_whatsapp_id');
            $tabla->unsignedBigInteger('chat_monitoreado_id');
            $tabla->string('estado', 32); // reservado | publicado | requiere_revision
            $tabla->string('origen', 24)->default('conciliacion');
            $tabla->dateTime('reservado_en', 6);
            $tabla->dateTime('publicado_en', 6)->nullable();
            $tabla->string('error_tipo', 120)->nullable();

            $tabla->unique('mensaje_whatsapp_id', 'mensajero_ia_publicacion_mensaje_uq');
            $tabla->index(['chat_monitoreado_id', 'reservado_en'], 'mensajero_ia_publicacion_grupo_fecha_idx');
            $tabla->foreign('mensaje_whatsapp_id', 'mensajero_ia_publicacion_mensaje_fk')
                ->references('id')->on('mensajero_mensajes_whatsapp')->onDelete('no action');
            $tabla->foreign('chat_monitoreado_id', 'mensajero_ia_publicacion_grupo_fk')
                ->references('id')->on('mensajero_chats_monitoreados')->onDelete('no action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensajero_control_publicaciones_gemini');
    }
};
