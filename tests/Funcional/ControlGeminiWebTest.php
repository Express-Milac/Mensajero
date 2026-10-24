<?php

namespace Tests\Funcional;

use App\Models\ChatMonitoreado;
use App\Services\IA\ControlInterpretacionGemini;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class ControlGeminiWebTest extends TestCase
{
    public function test_sin_permiso_especifico_no_se_puede_modificar_gemini(): void
    {
        config([
            'mensajero.autenticacion.modo' => 'deshabilitado',
            'mensajero.consulta_local_sin_autenticacion' => true,
            'mensajero.gestion_local_grupos_habilitada' => true,
            'mensajero.gestion_local_ia_habilitada' => false,
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->patch('/configuracion/gemini/0', ['habilitado' => '1', 'confirmo_envio' => '1'])
            ->assertForbidden();
    }

    public function test_la_red_remota_no_puede_modificar_gemini_aun_con_permiso_local(): void
    {
        config([
            'mensajero.autenticacion.modo' => 'deshabilitado',
            'mensajero.consulta_local_sin_autenticacion' => true,
            'mensajero.gestion_local_ia_habilitada' => true,
        ]);
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.22'])
            ->patch('/configuracion/gemini/0', ['habilitado' => '1', 'confirmo_envio' => '1'])
            ->assertForbidden();
    }

    public function test_el_interruptor_no_habilita_nada_sin_migracion(): void
    {
        Schema::shouldReceive('hasTable')
            ->with('mensajero_control_interpretacion_ia')
            ->andReturn(false);

        $control = new ControlInterpretacionGemini;
        $this->assertFalse($control->disponible());
        $this->assertFalse($control->permite(10197, CarbonImmutable::now('UTC')));
        $this->assertSame(['habilitado' => false, 'inicio_en' => null], $control->estado(0));
    }

    public function test_el_panel_indica_que_hay_dos_controles_separados_y_no_muestra_secretos(): void
    {
        $grupo = new ChatMonitoreado();
        $grupo->id = 10197;
        $grupo->nombre_interno = 'Logística MILAC';

        $filas = collect([[
            'grupo' => $grupo,
            'preautorizado' => true,
            'estado' => ['habilitado' => false, 'inicio_en' => null],
        ]]);
        $general = ['habilitado' => false, 'inicio_en' => null];
        $disponible = true;
        $listo = true;
        $maximo = 10;

        $html = view('ia.configuracion', compact('filas', 'general', 'disponible', 'listo', 'maximo'))->render();

        $this->assertStringContainsString('Interruptor general', $html);
        $this->assertStringContainsString('Interpretación por grupo', $html);
        $this->assertStringContainsString('Logística MILAC', $html);
        $this->assertStringContainsString('confirmo_envio', $html);
        $this->assertStringContainsString('Encender Gemini', $html);
        $this->assertStringContainsString('Activar', $html);
        $this->assertStringNotContainsString('CLAVE_API_GEMINI', $html);
    }

    public function test_la_activacion_requiere_confirmacion_del_usuario_sin_acceder_a_sql(): void
    {
        config([
            'mensajero.autenticacion.modo' => 'deshabilitado',
            'mensajero.consulta_local_sin_autenticacion' => true,
            'mensajero.gestion_local_ia_habilitada' => true,
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->from('/configuracion/gemini')
            ->patch('/configuracion/gemini/0', ['habilitado' => '1'])
            ->assertRedirect('/configuracion/gemini')
            ->assertSessionHasErrors('habilitado');
    }
}
