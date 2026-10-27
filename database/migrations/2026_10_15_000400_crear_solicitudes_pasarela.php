<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mensajero_solicitudes_pasarela', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('identificador_solicitud', 128)->collation('Latin1_General_100_BIN2');
            $tabla->bigInteger('marca_tiempo_pasarela');
            $tabla->char('hash_cuerpo_sha256', 64);
            $tabla->string('ruta', 191);
            $tabla->dateTime('recibido_en', 6)->useCurrent();
            $tabla->unique('identificador_solicitud', 'mensajero_solicitudes_pasarela_identificador_uq');
            $tabla->index('recibido_en', 'mensajero_solicitudes_pasarela_recibido_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensajero_solicitudes_pasarela');
    }
};
