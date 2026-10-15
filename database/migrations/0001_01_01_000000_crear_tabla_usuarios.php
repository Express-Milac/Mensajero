<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mensajero_usuarios', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('nombre');
            $tabla->string('correo')->unique();
            $tabla->dateTime('correo_verificado_en', 6)->nullable();
            $tabla->string('clave');
            $tabla->string('rol', 24)->default('visualizador');
            $tabla->string('recordar_token', 100)->nullable();
            $tabla->dateTime('creado_en', 6);
            $tabla->dateTime('actualizado_en', 6);
        });

        Schema::create('mensajero_tokens_restablecimiento', function (Blueprint $tabla) {
            $tabla->string('correo')->primary();
            $tabla->string('token');
            $tabla->dateTime('creado_en', 6)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensajero_tokens_restablecimiento');
        Schema::dropIfExists('mensajero_usuarios');
    }
};
