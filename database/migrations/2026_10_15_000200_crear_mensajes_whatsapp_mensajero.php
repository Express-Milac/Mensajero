<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mensajero_mensajes_whatsapp', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->bigInteger('conexion_id');
            $tabla->bigInteger('chat_monitoreado_id');
            $tabla->bigInteger('contacto_remitente_id')->nullable();
            $tabla->bigInteger('evento_crudo_id');
            $tabla->string('id_mensaje_proveedor', 191)->collation('Latin1_General_100_BIN2');
            $tabla->string('tipo_mensaje', 32);
            $tabla->longText('contenido_texto')->nullable();
            $tabla->longText('pie_multimedia')->nullable();
            $tabla->dateTime('enviado_en', 6)->nullable();
            $tabla->dateTime('recibido_en', 6);
            $tabla->dateTime('procesado_en', 6)->nullable();
            $tabla->string('id_mensaje_citado_proveedor', 191)->nullable()->collation('Latin1_General_100_BIN2');
            $tabla->bigInteger('mensaje_citado_id')->nullable();
            $tabla->string('estado_captura', 24)->default('normalizado');
            $tabla->string('estado_multimedia', 24)->default('no_requerido');
            $tabla->string('estado_ia', 24)->default('no_elegible');
            $tabla->dateTime('creado_en', 6)->nullable();
            $tabla->dateTime('actualizado_en', 6)->nullable();
            $tabla->unique(['conexion_id', 'chat_monitoreado_id', 'id_mensaje_proveedor'], 'mensajero_mensajes_proveedor_uq');
            $tabla->unique('evento_crudo_id', 'mensajero_mensajes_evento_uq');
            $tabla->index(['chat_monitoreado_id', 'enviado_en', 'id'], 'mensajero_mensajes_chat_enviado_idx');
            $tabla->index(['contacto_remitente_id', 'enviado_en'], 'mensajero_mensajes_remitente_enviado_idx');
            $tabla->foreign('conexion_id', 'mensajero_mensajes_conexion_fk')->references('id')->on('mensajero_conexiones_whatsapp')->onDelete('no action');
            $tabla->foreign('chat_monitoreado_id', 'mensajero_mensajes_chat_fk')->references('id')->on('mensajero_chats_monitoreados')->onDelete('no action');
            $tabla->foreign('contacto_remitente_id', 'mensajero_mensajes_remitente_fk')->references('id')->on('mensajero_contactos_whatsapp')->onDelete('no action');
            $tabla->foreign('evento_crudo_id', 'mensajero_mensajes_evento_fk')->references('id')->on('mensajero_eventos_crudos_whatsapp')->onDelete('no action');
            $tabla->foreign('mensaje_citado_id', 'mensajero_mensajes_citado_fk')->references('id')->on('mensajero_mensajes_whatsapp')->onDelete('no action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensajero_mensajes_whatsapp');
    }
};
