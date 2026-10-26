<?php

namespace Tests\Funcional;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

final class ProyectarOperacionTest extends TestCase
{
    public function test_simulacion_todos_consulta_solo_grupos_autorizados_sin_escrituras_ni_google(): void
    {
        Http::fake();

        // El comando se prueba sin una conexión real a SQL Server.
        Schema::shouldReceive('hasTable')->with('mensajero_acontecimientos_operativos')->once()->andReturn(true);
        Schema::shouldReceive('hasTable')->with('mensajero_analisis_operativos_ia')->once()->andReturn(true);
        Schema::shouldReceive('hasTable')->with('mensajero_control_interpretacion_ia')->once()->andReturn(true);

        $controles = Mockery::mock();
        $controles->shouldReceive('join')->once()->andReturnSelf();
        $controles->shouldReceive('where')->times(5)->andReturnSelf();
        $controles->shouldReceive('orderBy')->once()->andReturnSelf();
        $controles->shouldReceive('pluck')->once()->with('control.chat_monitoreado_id')
            ->andReturn(collect([10196]));

        $grupo = Mockery::mock();
        $grupo->shouldReceive('where')->times(5)->andReturnSelf();
        $grupo->shouldReceive('exists')->once()->andReturn(true);

        $analisis = Mockery::mock();
        $analisis->shouldReceive('where')->once()->andReturnSelf();
        $analisis->shouldReceive('whereIn')->once()->andReturnSelf();
        $analisis->shouldReceive('whereNotExists')->once()->andReturnSelf();
        $analisis->shouldReceive('orderBy')->once()->andReturnSelf();
        $analisis->shouldReceive('limit')->once()->with(3)->andReturnSelf();
        $analisis->shouldReceive('get')->once()->with(['a.id', 'a.mensaje_whatsapp_id'])
            ->andReturn(collect([(object) ['id' => 77, 'mensaje_whatsapp_id' => 501]]));

        DB::shouldReceive('table')->once()->with('mensajero_control_interpretacion_ia as control')
            ->andReturn($controles);
        DB::shouldReceive('table')->once()->with('mensajero_chats_monitoreados')
            ->andReturn($grupo);
        DB::shouldReceive('table')->once()->with('mensajero_analisis_operativos_ia as a')
            ->andReturn($analisis);

        $this->artisan('mensajero:proyectar-operacion', ['--todos' => true, '--limite' => '3'])
            ->expectsOutput('Grupo 10196 | análisis sin acontecimiento: 1')
            ->expectsOutput('Análisis #77 | Mensaje #501')
            ->assertExitCode(0);

        Http::assertNothingSent();
    }
}
