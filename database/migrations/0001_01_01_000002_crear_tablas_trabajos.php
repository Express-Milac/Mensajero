<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Las columnas conservan los nombres que exige el controlador de colas de Laravel.
        Schema::create('mensajero_trabajos', function (Blueprint $tabla) {
            $tabla->id(); $tabla->string('queue')->index(); $tabla->longText('payload'); $tabla->unsignedSmallInteger('attempts');
            $tabla->unsignedInteger('reserved_at')->nullable(); $tabla->unsignedInteger('available_at'); $tabla->unsignedInteger('created_at');
        });
        Schema::create('mensajero_lotes_trabajos', function (Blueprint $tabla) {
            $tabla->string('id')->primary(); $tabla->string('name'); $tabla->integer('total_jobs'); $tabla->integer('pending_jobs');
            $tabla->integer('failed_jobs'); $tabla->longText('failed_job_ids'); $tabla->mediumText('options')->nullable();
            $tabla->integer('cancelled_at')->nullable(); $tabla->integer('created_at'); $tabla->integer('finished_at')->nullable();
        });
        Schema::create('mensajero_trabajos_fallidos', function (Blueprint $tabla) {
            $tabla->id(); $tabla->string('uuid')->unique(); $tabla->string('connection'); $tabla->string('queue');
            $tabla->longText('payload'); $tabla->longText('exception'); $tabla->dateTime('failed_at', 6)->useCurrent();
            $tabla->index(['connection', 'queue', 'failed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensajero_trabajos');
        Schema::dropIfExists('mensajero_lotes_trabajos');
        Schema::dropIfExists('mensajero_trabajos_fallidos');
    }
};
