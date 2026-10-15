<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mensajero_conexiones_whatsapp', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('clave_conexion', 64)->unique();
            $tabla->string('proveedor', 32);
            $tabla->string('id_cuenta_externa', 191)->nullable();
            $tabla->string('nombre_visible')->nullable();
            $tabla->string('estado', 32)->default('sin_configurar');
            $tabla->longText('metadatos')->nullable();
            $tabla->dateTime('conectado_en', 6)->nullable();
            $tabla->dateTime('ultimo_latido_en', 6)->nullable();
            $tabla->dateTime('ultima_desconexion_en', 6)->nullable();
            $tabla->dateTime('creado_en', 6)->nullable();
            $tabla->dateTime('actualizado_en', 6)->nullable();
            $tabla->index(['proveedor', 'estado'], 'mensajero_conexiones_proveedor_estado_idx');
        });

        Schema::create('mensajero_chats_monitoreados', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->bigInteger('conexion_id');
            $tabla->string('id_chat_proveedor', 191)->collation('Latin1_General_100_BIN2');
            $tabla->string('tipo_chat', 32);
            $tabla->string('nombre_grupo')->nullable();
            $tabla->string('nombre_interno')->nullable();
            $tabla->boolean('es_monitoreado')->default(false);
            $tabla->boolean('es_activo')->default(true);
            $tabla->bigInteger('version_autorizacion')->default(0);
            $tabla->dateTime('visto_primera_vez_en', 6)->nullable();
            $tabla->dateTime('visto_ultima_vez_en', 6)->nullable();
            $tabla->dateTime('creado_en', 6)->nullable();
            $tabla->dateTime('actualizado_en', 6)->nullable();
            $tabla->unique(['conexion_id', 'id_chat_proveedor'], 'mensajero_chats_conexion_proveedor_uq');
            $tabla->index(['es_monitoreado', 'es_activo'], 'mensajero_chats_monitoreo_idx');
            $tabla->foreign('conexion_id', 'mensajero_chats_conexion_fk')->references('id')->on('mensajero_conexiones_whatsapp')->onDelete('no action');
        });

        Schema::create('mensajero_contactos_whatsapp', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->bigInteger('conexion_id');
            $tabla->string('id_contacto_proveedor', 191)->collation('Latin1_General_100_BIN2');
            $tabla->string('telefono', 32)->nullable();
            $tabla->string('nombre_visible')->nullable();
            $tabla->string('nombre_notificacion')->nullable();
            $tabla->longText('metadatos')->nullable();
            $tabla->dateTime('visto_primera_vez_en', 6)->nullable();
            $tabla->dateTime('visto_ultima_vez_en', 6)->nullable();
            $tabla->dateTime('creado_en', 6)->nullable();
            $tabla->dateTime('actualizado_en', 6)->nullable();
            $tabla->unique(['conexion_id', 'id_contacto_proveedor'], 'mensajero_contactos_conexion_proveedor_uq');
            $tabla->index('telefono', 'mensajero_contactos_telefono_idx');
            $tabla->foreign('conexion_id', 'mensajero_contactos_conexion_fk')->references('id')->on('mensajero_conexiones_whatsapp')->onDelete('no action');
        });

        Schema::create('mensajero_eventos_crudos_whatsapp', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->bigInteger('conexion_id');
            $tabla->bigInteger('chat_monitoreado_id');
            $tabla->string('id_evento_externo', 191)->nullable()->collation('Latin1_General_100_BIN2');
            $tabla->char('clave_deduplicacion', 64);
            $tabla->string('tipo_evento', 64);
            $tabla->longText('contenido');
            $tabla->char('hash_contenido_sha256', 64);
            $tabla->string('version_contrato', 32)->default('v1');
            $tabla->string('estado_procesamiento', 24)->default('capturado');
            $tabla->unsignedSmallInteger('intentos')->default(0);
            $tabla->dateTime('recibido_en', 6);
            $tabla->dateTime('procesado_en', 6)->nullable();
            $tabla->longText('mensaje_error')->nullable();
            $tabla->dateTime('creado_en', 6)->nullable();
            $tabla->dateTime('actualizado_en', 6)->nullable();
            $tabla->unique(['conexion_id', 'clave_deduplicacion'], 'mensajero_eventos_conexion_deduplicacion_uq');
            $tabla->index(['chat_monitoreado_id', 'recibido_en', 'id'], 'mensajero_eventos_chat_recibido_idx');
            $tabla->index(['estado_procesamiento', 'recibido_en'], 'mensajero_eventos_estado_recibido_idx');
            $tabla->foreign('conexion_id', 'mensajero_eventos_conexion_fk')->references('id')->on('mensajero_conexiones_whatsapp')->onDelete('no action');
            $tabla->foreign('chat_monitoreado_id', 'mensajero_eventos_chat_fk')->references('id')->on('mensajero_chats_monitoreados')->onDelete('no action');
        });

        Schema::create('mensajero_buzon_salida', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('tema', 128);
            $tabla->string('tipo_entidad', 64);
            $tabla->bigInteger('entidad_id')->nullable();
            $tabla->char('clave_deduplicacion', 64);
            $tabla->longText('contenido');
            $tabla->unsignedSmallInteger('intentos')->default(0);
            $tabla->dateTime('disponible_en', 6);
            $tabla->dateTime('reservado_hasta', 6)->nullable();
            $tabla->dateTime('enviado_en', 6)->nullable();
            $tabla->longText('ultimo_error')->nullable();
            $tabla->dateTime('creado_en', 6)->useCurrent();
            $tabla->unique(['tema', 'clave_deduplicacion'], 'mensajero_buzon_tema_deduplicacion_uq');
            $tabla->index(['enviado_en', 'disponible_en', 'id'], 'mensajero_buzon_envio_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensajero_buzon_salida');
        Schema::dropIfExists('mensajero_eventos_crudos_whatsapp');
        Schema::dropIfExists('mensajero_contactos_whatsapp');
        Schema::dropIfExists('mensajero_chats_monitoreados');
        Schema::dropIfExists('mensajero_conexiones_whatsapp');
    }
};
