<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mensajero_identidades_remitentes', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->bigInteger('conexion_id');
            $tabla->string('id_remitente_proveedor', 191)->collation('Latin1_General_100_BIN2');
            $tabla->string('nombre_interno', 120);
            $tabla->string('actor_tipo', 32);
            $tabla->unsignedBigInteger('actor_id')->nullable();
            $tabla->dateTime('creado_en', 6)->nullable();
            $tabla->dateTime('actualizado_en', 6)->nullable();
            $tabla->unique(['conexion_id', 'id_remitente_proveedor'], 'mensajero_identidad_conexion_remitente_uq');
            $tabla->foreign('conexion_id', 'mensajero_identidad_conexion_fk')
                ->references('id')->on('mensajero_conexiones_whatsapp')->onDelete('no action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensajero_identidades_remitentes');
    }
};
