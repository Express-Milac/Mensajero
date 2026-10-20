<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nueva preferencia de presentación, independiente de es_monitoreado:
        // jamás reconfigura los grupos que captura el recolector.
        Schema::table('mensajero_chats_monitoreados', function (Blueprint $tabla): void {
            $tabla->boolean('es_visible_tablero')->default(true);
        });

        Schema::create('mensajero_auditoria_visibilidad_grupos', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->unsignedBigInteger('chat_monitoreado_id');
            $tabla->boolean('visible_anterior');
            $tabla->boolean('visible_nuevo');
            $tabla->string('actor_tipo', 32);
            $tabla->unsignedBigInteger('actor_id')->nullable();
            $tabla->dateTime('registrado_en', 6);
            $tabla->index(['chat_monitoreado_id', 'registrado_en'], 'mensajero_auditoria_visibilidad_idx');
            $tabla->foreign('chat_monitoreado_id', 'mensajero_auditoria_visibilidad_chat_fk')
                ->references('id')->on('mensajero_chats_monitoreados')->onDelete('no action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensajero_auditoria_visibilidad_grupos');

        Schema::table('mensajero_chats_monitoreados', function (Blueprint $tabla): void {
            $tabla->dropColumn('es_visible_tablero');
        });
    }
};
