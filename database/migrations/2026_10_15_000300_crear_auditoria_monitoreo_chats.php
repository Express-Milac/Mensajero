<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mensajero_auditoria_monitoreo_chats', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->bigInteger('chat_monitoreado_id');
            $tabla->bigInteger('version_autorizacion');
            $tabla->boolean('es_monitoreado_anterior');
            $tabla->boolean('es_monitoreado_nuevo');
            $tabla->string('actor_tipo', 32);
            $tabla->bigInteger('actor_id')->nullable();
            $tabla->string('motivo', 500)->nullable();
            $tabla->dateTime('cambiado_en', 6)->useCurrent();

            $tabla->unique(
                ['chat_monitoreado_id', 'version_autorizacion'],
                'mensajero_auditoria_chat_version_uq',
            );
            $tabla->index(['cambiado_en', 'id'], 'mensajero_auditoria_cambiado_idx');
            $tabla->foreign('chat_monitoreado_id', 'mensajero_auditoria_chat_fk')
                ->references('id')
                ->on('mensajero_chats_monitoreados')
                ->onDelete('no action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensajero_auditoria_monitoreo_chats');
    }
};
