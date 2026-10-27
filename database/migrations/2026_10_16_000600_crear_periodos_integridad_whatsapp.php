<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mensajero_periodos_integridad_whatsapp', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->bigInteger('conexion_id');
            $tabla->char('identificador_periodo', 64);
            $tabla->dateTime('inicio_en', 6);
            $tabla->dateTime('fin_en', 6);
            $tabla->string('causa', 64);
            $tabla->longText('detalles')->nullable();
            $tabla->dateTime('creado_en', 6)->nullable();
            $tabla->dateTime('actualizado_en', 6)->nullable();
            $tabla->unique(['conexion_id', 'identificador_periodo'], 'mensajero_integridad_conexion_periodo_uq');
            $tabla->index(['conexion_id', 'inicio_en', 'fin_en'], 'mensajero_integridad_conexion_fechas_idx');
            $tabla->foreign('conexion_id', 'mensajero_integridad_conexion_fk')
                ->references('id')
                ->on('mensajero_conexiones_whatsapp')
                ->onDelete('no action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensajero_periodos_integridad_whatsapp');
    }
};
