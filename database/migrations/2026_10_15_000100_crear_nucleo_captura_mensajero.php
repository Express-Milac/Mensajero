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
            $tabla->dateTime('ultima_senal_en', 6)->nullable();
            $tabla->dateTime('ultima_desconexion_en', 6)->nullable();
            $tabla->dateTime('creado_en', 6);
            $tabla->dateTime('actualizado_en', 6);
            $tabla->index(['proveedor', 'estado'], 'mensajero_conexion_estado_ind');
        });

        Schema::create('mensajero_conversaciones_monitoreadas', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->bigInteger('conexion_id');
            $tabla->string('id_conversacion_proveedor', 191)->collation('Latin1_General_100_BIN2');
            $tabla->string('tipo_conversacion', 32);
            $tabla->string('nombre_grupo')->nullable();
            $tabla->string('nombre_interno')->nullable();
            $tabla->boolean('esta_monitoreada')->default(false);
            $tabla->boolean('esta_activa')->default(true);
            $tabla->bigInteger('version_autorizacion')->default(0);
            $tabla->dateTime('primera_vez_vista_en', 6)->nullable();
            $tabla->dateTime('ultima_vez_vista_en', 6)->nullable();
            $tabla->dateTime('creado_en', 6);
            $tabla->dateTime('actualizado_en', 6);
            $tabla->unique(['conexion_id', 'id_conversacion_proveedor'], 'mensajero_conversacion_proveedor_unico');
            $tabla->index(['esta_monitoreada', 'esta_activa'], 'mensajero_conversacion_monitoreo_ind');
            $tabla->foreign('conexion_id', 'rel_conversacion_conexion')->references('id')->on('mensajero_conexiones_whatsapp')->onDelete('no action');
        });

        Schema::create('mensajero_contactos_whatsapp', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->bigInteger('conexion_id');
            $tabla->string('id_contacto_proveedor', 191)->collation('Latin1_General_100_BIN2');
            $tabla->string('telefono', 32)->nullable();
            $tabla->string('nombre_visible')->nullable();
            $tabla->string('nombre_perfil')->nullable();
            $tabla->longText('metadatos')->nullable();
            $tabla->dateTime('primera_vez_visto_en', 6)->nullable();
            $tabla->dateTime('ultima_vez_visto_en', 6)->nullable();
            $tabla->dateTime('creado_en', 6);
            $tabla->dateTime('actualizado_en', 6);
            $tabla->unique(['conexion_id', 'id_contacto_proveedor'], 'mensajero_contacto_proveedor_unico');
            $tabla->index('telefono', 'mensajero_contacto_telefono_ind');
            $tabla->foreign('conexion_id', 'rel_contacto_conexion')->references('id')->on('mensajero_conexiones_whatsapp')->onDelete('no action');
        });

        Schema::create('mensajero_eventos_crudos_whatsapp', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->bigInteger('conexion_id');
            $tabla->bigInteger('conversacion_monitoreada_id');
            $tabla->string('id_evento_externo', 191)->nullable()->collation('Latin1_General_100_BIN2');
            $tabla->char('clave_deduplicacion', 64);
            $tabla->string('tipo_evento', 64);
            $tabla->longText('contenido_crudo');
            $tabla->char('hash_contenido_sha256', 64);
            $tabla->string('version_contrato', 32)->default('v1');
            $tabla->string('estado_procesamiento', 24)->default('capturado');
            $tabla->unsignedSmallInteger('intentos')->default(0);
            $tabla->dateTime('recibido_en', 6);
            $tabla->dateTime('procesado_en', 6)->nullable();
            $tabla->longText('mensaje_error')->nullable();
            $tabla->dateTime('creado_en', 6);
            $tabla->dateTime('actualizado_en', 6);
            $tabla->unique(['conexion_id', 'clave_deduplicacion'], 'mensajero_crudo_conexion_dedup_unico');
            $tabla->index(['conversacion_monitoreada_id', 'recibido_en', 'id'], 'mensajero_crudo_conversacion_recibido_ind');
            $tabla->index(['estado_procesamiento', 'recibido_en'], 'mensajero_crudo_estado_recibido_ind');
            $tabla->foreign('conexion_id', 'rel_crudo_conexion')->references('id')->on('mensajero_conexiones_whatsapp')->onDelete('no action');
            $tabla->foreign('conversacion_monitoreada_id', 'rel_crudo_conversacion')->references('id')->on('mensajero_conversaciones_monitoreadas')->onDelete('no action');
        });

        Schema::create('mensajero_bandeja_salida', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('tema', 128);
            $tabla->string('tipo_entidad', 64);
            $tabla->bigInteger('entidad_id')->nullable();
            $tabla->char('clave_deduplicacion', 64);
            $tabla->longText('contenido');
            $tabla->unsignedSmallInteger('intentos')->default(0);
            $tabla->dateTime('disponible_en', 6);
            $tabla->dateTime('reservado_hasta', 6)->nullable();
            $tabla->dateTime('despachado_en', 6)->nullable();
            $tabla->longText('ultimo_error')->nullable();
            $tabla->dateTime('creado_en', 6);
            $tabla->dateTime('actualizado_en', 6);
            $tabla->unique(['tema', 'clave_deduplicacion'], 'mensajero_salida_tema_dedup_unico');
            $tabla->index(['despachado_en', 'disponible_en', 'id'], 'mensajero_salida_despacho_ind');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensajero_bandeja_salida');
        Schema::dropIfExists('mensajero_eventos_crudos_whatsapp');
        Schema::dropIfExists('mensajero_contactos_whatsapp');
        Schema::dropIfExists('mensajero_conversaciones_monitoreadas');
        Schema::dropIfExists('mensajero_conexiones_whatsapp');
    }
};
