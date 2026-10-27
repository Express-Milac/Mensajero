<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mensajero_eventos_crudos_whatsapp', function (Blueprint $tabla) {
            $tabla->string('tipo_mensaje', 64)->nullable();
            $tabla->string('id_remitente_proveedor', 191)->nullable()->collation('Latin1_General_100_BIN2');
            $tabla->string('id_mensaje_citado_proveedor', 191)->nullable()->collation('Latin1_General_100_BIN2');
            $tabla->string('id_mensaje_objetivo_proveedor', 191)->nullable()->collation('Latin1_General_100_BIN2');

            $tabla->index(
                ['chat_monitoreado_id', 'tipo_evento', 'tipo_mensaje', 'recibido_en'],
                'mensajero_eventos_chat_tipo_recibido_idx',
            );
            $tabla->index(
                ['chat_monitoreado_id', 'id_remitente_proveedor', 'recibido_en'],
                'mensajero_eventos_chat_remitente_idx',
            );
            $tabla->index(
                ['chat_monitoreado_id', 'id_mensaje_objetivo_proveedor'],
                'mensajero_eventos_chat_objetivo_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('mensajero_eventos_crudos_whatsapp', function (Blueprint $tabla) {
            $tabla->dropIndex('mensajero_eventos_chat_tipo_recibido_idx');
            $tabla->dropIndex('mensajero_eventos_chat_remitente_idx');
            $tabla->dropIndex('mensajero_eventos_chat_objetivo_idx');

            $tabla->dropColumn([
                'tipo_mensaje',
                'id_remitente_proveedor',
                'id_mensaje_citado_proveedor',
                'id_mensaje_objetivo_proveedor',
            ]);
        });
    }
};
