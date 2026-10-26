<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mensajero_control_interpretacion_ia', function (Blueprint $tabla): void {
            // 0 = control global; los demás son ID internos de grupos autorizados.
            $tabla->bigInteger('chat_monitoreado_id')->primary();
            $tabla->boolean('habilitado')->default(false);
            $tabla->dateTime('inicio_en', 6)->nullable();
            $tabla->dateTime('actualizado_en', 6);
        });

        Schema::create('mensajero_auditoria_interpretacion_ia', function (Blueprint $tabla): void {
            $tabla->bigIncrements('id');
            $tabla->bigInteger('chat_monitoreado_id');
            $tabla->boolean('habilitado_anterior');
            $tabla->boolean('habilitado_nuevo');
            $tabla->string('actor_tipo', 40);
            $tabla->bigInteger('actor_id')->nullable();
            $tabla->dateTime('registrado_en', 6);
            $tabla->index(['chat_monitoreado_id', 'registrado_en'], 'mensajero_auditoria_ia_grupo_fecha');
        });

        // Estado seguro de instalación: TODO apagado. La activación será desde UI.
        DB::table('mensajero_control_interpretacion_ia')->insert([
            'chat_monitoreado_id' => 0,
            'habilitado' => false,
            'inicio_en' => null,
            'actualizado_en' => now('UTC')->format('Y-m-d H:i:s.u'),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('mensajero_auditoria_interpretacion_ia');
        Schema::dropIfExists('mensajero_control_interpretacion_ia');
    }
};
