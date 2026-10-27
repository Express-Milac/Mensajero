<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mensajero_conexiones_whatsapp', function (Blueprint $tabla) {
            $tabla->index(
                ['proveedor', 'id_cuenta_externa'],
                'mensajero_conexiones_proveedor_externa_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('mensajero_conexiones_whatsapp', function (Blueprint $tabla) {
            $tabla->dropIndex('mensajero_conexiones_proveedor_externa_idx');
        });
    }
};
