<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mensajero_archivos_whatsapp', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->bigInteger('conexion_id');
            $tabla->bigInteger('chat_monitoreado_id');
            $tabla->bigInteger('evento_crudo_id');
            $tabla->char('identificador_archivo', 64);
            $tabla->string('id_mensaje_proveedor', 191)->nullable()->collation('Latin1_General_100_BIN2');
            $tabla->string('tipo_mensaje', 32);
            $tabla->string('tipo_mime', 191)->nullable();
            $tabla->string('nombre_original')->nullable();
            $tabla->string('extension', 16)->nullable();
            $tabla->bigInteger('tamano_bytes');
            $tabla->char('hash_sha256', 64);
            $tabla->string('disco', 64)->default('mensajero_local');
            $tabla->string('ruta_relativa', 1024);
            $tabla->string('estado', 24)->default('verificado');
            $tabla->dateTime('creado_en', 6)->nullable();
            $tabla->dateTime('actualizado_en', 6)->nullable();

            $tabla->unique(['conexion_id', 'identificador_archivo'], 'mensajero_archivos_conexion_identificador_uq');
            $tabla->unique('evento_crudo_id', 'mensajero_archivos_evento_uq');
            $tabla->index(['chat_monitoreado_id', 'creado_en'], 'mensajero_archivos_chat_creado_idx');
            $tabla->index('hash_sha256', 'mensajero_archivos_hash_idx');

            $tabla->foreign('conexion_id', 'mensajero_archivos_conexion_fk')
                ->references('id')
                ->on('mensajero_conexiones_whatsapp')
                ->onDelete('no action');
            $tabla->foreign('chat_monitoreado_id', 'mensajero_archivos_chat_fk')
                ->references('id')
                ->on('mensajero_chats_monitoreados')
                ->onDelete('no action');
            $tabla->foreign('evento_crudo_id', 'mensajero_archivos_evento_fk')
                ->references('id')
                ->on('mensajero_eventos_crudos_whatsapp')
                ->onDelete('no action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensajero_archivos_whatsapp');
    }
};
