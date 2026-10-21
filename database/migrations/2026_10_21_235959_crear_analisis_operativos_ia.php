<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mensajero_analisis_operativos_ia', function (Blueprint $tabla): void {
            $tabla->bigIncrements('id');
            $tabla->unsignedBigInteger('mensaje_whatsapp_id');
            $tabla->unsignedBigInteger('chat_monitoreado_id');
            $tabla->string('estado', 32); // pendiente_revision | sin_evento
            $tabla->string('categoria', 40)->nullable();
            $tabla->string('titulo', 180)->nullable();
            $tabla->text('resumen')->nullable();
            $tabla->longText('evidencias_json');
            $tabla->string('modelo', 90);
            $tabla->string('hash_contexto', 64);
            $tabla->unsignedInteger('tokens_entrada')->nullable();
            $tabla->unsignedInteger('tokens_salida')->nullable();
            $tabla->dateTime('creado_en', 6);

            $tabla->unique('mensaje_whatsapp_id', 'mensajero_ia_mensaje_unico');
            $tabla->index(['chat_monitoreado_id', 'creado_en'], 'mensajero_ia_grupo_fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensajero_analisis_operativos_ia');
    }
};
