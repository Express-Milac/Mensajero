<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mensajero_eventos_crudos_whatsapp', function (Blueprint $tabla): void {
            // Datos opcionales del recolector. La evidencia JSON original,
            // su hash y la clave de deduplicación nunca se sobrescriben.
            $tabla->string('nombre_remitente', 120)->nullable();
            $tabla->string('fuente_nombre_remitente', 16)->nullable();
            $tabla->string('telefono_remitente', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('mensajero_eventos_crudos_whatsapp', function (Blueprint $tabla): void {
            $tabla->dropColumn([
                'nombre_remitente',
                'fuente_nombre_remitente',
                'telefono_remitente',
            ]);
        });
    }
};
