<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registro contable de reservas estimadas, INACTIVO por defecto.
 * No activa IA, no publica trabajos, no modifica lotes ni mensajes existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mensajero_candado_presupuesto_gemini', function (Blueprint $tabla): void {
            $tabla->unsignedTinyInteger('id')->primary();
            $tabla->dateTime('creado_en', 6);
        });

        DB::table('mensajero_candado_presupuesto_gemini')->insert([
            'id' => 1,
            'creado_en' => now('UTC')->format('Y-m-d H:i:s.u'),
        ]);

        Schema::create('mensajero_reservas_consumo_gemini', function (Blueprint $tabla): void {
            $tabla->bigIncrements('id');
            $tabla->unsignedBigInteger('chat_monitoreado_id');
            $tabla->string('tipo_fuente', 16); // mensaje | lote
            $tabla->unsignedBigInteger('fuente_id');
            $tabla->string('modelo', 90);
            $tabla->string('tarifa_referencia', 90);
            // Enteros: 1 USD = 1 000 000 microUSD. Nunca FLOAT para dinero.
            $tabla->unsignedBigInteger('importe_reservado_microusd');
            $tabla->string('estado', 24)->default('reservada'); // reservada | incierta | finalizada
            $tabla->dateTime('reservado_en', 6);
            $tabla->dateTime('actualizado_en', 6);

            $tabla->unique(['tipo_fuente', 'fuente_id'], 'mensajero_gemini_reserva_fuente_uq');
            $tabla->index('reservado_en', 'mensajero_gemini_reserva_fecha_idx');
            $tabla->index(['chat_monitoreado_id', 'reservado_en'], 'mensajero_gemini_reserva_grupo_fecha_idx');
            $tabla->foreign('chat_monitoreado_id', 'mensajero_gemini_reserva_grupo_fk')
                ->references('id')->on('mensajero_chats_monitoreados')->onDelete('no action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensajero_reservas_consumo_gemini');
        Schema::dropIfExists('mensajero_candado_presupuesto_gemini');
    }
};
