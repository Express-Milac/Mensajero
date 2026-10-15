<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Las columnas de autenticación conservan nombres exigidos por Laravel.
        Schema::create('mensajero_usuarios', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('name');
            $tabla->string('email')->unique();
            $tabla->dateTime('email_verified_at', 6)->nullable();
            $tabla->string('password');
            $tabla->string('rol', 24)->default('visualizador');
            $tabla->rememberToken();
            $tabla->timestamps();
        });

        Schema::create('mensajero_tokens_restablecimiento_contrasena', function (Blueprint $tabla) {
            $tabla->string('email')->primary();
            $tabla->string('token');
            $tabla->dateTime('created_at', 6)->nullable();
        });

        Schema::create('mensajero_sesiones', function (Blueprint $tabla) {
            $tabla->string('id')->primary();
            $tabla->bigInteger('user_id')->nullable()->index();
            $tabla->string('ip_address', 45)->nullable();
            $tabla->text('user_agent')->nullable();
            $tabla->longText('payload');
            $tabla->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensajero_sesiones');
        Schema::dropIfExists('mensajero_tokens_restablecimiento_contrasena');
        Schema::dropIfExists('mensajero_usuarios');
    }
};
