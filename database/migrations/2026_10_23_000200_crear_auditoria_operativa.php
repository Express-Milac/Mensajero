<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mensajero_auditoria_acontecimientos', function (Blueprint $tabla): void {
            $tabla->bigIncrements('id');
            $tabla->unsignedBigInteger('acontecimiento_id');
            $tabla->string('estado_anterior', 32);
            $tabla->string('estado_nuevo', 32);
            $tabla->string('actor_tipo', 40);
            $tabla->unsignedBigInteger('actor_id')->nullable();
            $tabla->dateTime('registrado_en', 6);
            $tabla->index(['acontecimiento_id', 'registrado_en'], 'mensajero_auditoria_operativa_idx');
            $tabla->foreign('acontecimiento_id', 'mensajero_auditoria_operativa_acontecimiento_fk')
                ->references('id')->on('mensajero_acontecimientos_operativos')->onDelete('no action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensajero_auditoria_acontecimientos');
    }
};
