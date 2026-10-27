<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Los nombres de columnas son parte del contrato interno del controlador de caché de Laravel.
        Schema::create('mensajero_cache', function (Blueprint $tabla) {
            $tabla->string('key')->primary();
            $tabla->mediumText('value');
            $tabla->bigInteger('expiration')->index();
        });

        Schema::create('mensajero_bloqueos_cache', function (Blueprint $tabla) {
            $tabla->string('key')->primary();
            $tabla->string('owner');
            $tabla->bigInteger('expiration')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensajero_bloqueos_cache');
        Schema::dropIfExists('mensajero_cache');
    }
};
