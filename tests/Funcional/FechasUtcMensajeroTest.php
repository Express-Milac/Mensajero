<?php

namespace Tests\Funcional;

use App\Models\ConexionWhatsapp;
use App\Models\EventoCrudoWhatsapp;
use App\Models\PeriodoIntegridadWhatsapp;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class FechasUtcMensajeroTest extends TestCase
{
    public function test_los_datetime_de_sql_server_se_interpretan_como_utc_sin_sumar_seis_horas(): void
    {
        config(['app.timezone' => 'America/Mexico_City']);

        $conexion = new ConexionWhatsapp;
        $conexion->setRawAttributes(['ultimo_latido_en' => '2026-10-19 15:32:10.000000']);

        $this->assertSame(
            '2026-10-19T15:32:10+00:00',
            $conexion->ultimo_latido_en->toIso8601String(),
        );

        $evento = new EventoCrudoWhatsapp;
        $evento->setRawAttributes(['recibido_en' => '2026-10-19 14:15:26.000000']);
        $this->assertSame(
            '2026-10-19T14:15:26+00:00',
            $evento->recibido_en->toIso8601String(),
        );

        $periodo = new PeriodoIntegridadWhatsapp;
        $periodo->setRawAttributes([
            'inicio_en' => '2026-10-19 13:00:00.000000',
            'fin_en' => '2026-10-19 14:00:00.000000',
        ]);

        $this->assertSame('2026-10-19T13:00:00+00:00', $periodo->inicio_en->toIso8601String());
        $this->assertSame('2026-10-19T14:00:00+00:00', $periodo->fin_en->toIso8601String());
    }

    public function test_fechas_con_zona_explicita_se_convierten_a_utc_al_persistir(): void
    {
        config(['app.timezone' => 'America/Mexico_City']);

        $conexion = new ConexionWhatsapp;
        $conexion->ultimo_latido_en = CarbonImmutable::parse('2026-10-19T11:19:32-06:00');

        $this->assertSame(
            '2026-10-19 17:19:32.000000',
            $conexion->getAttributes()['ultimo_latido_en'],
        );
        $this->assertSame(
            '2026-10-19T17:19:32+00:00',
            $conexion->ultimo_latido_en->toIso8601String(),
        );

        $evento = new EventoCrudoWhatsapp;
        $evento->recibido_en = '2026-10-19T11:19:32-06:00';
        $this->assertSame(
            '2026-10-19 17:19:32.000000',
            $evento->getAttributes()['recibido_en'],
        );
    }

    public function test_fechas_nulas_se_conservan_nulas(): void
    {
        $conexion = new ConexionWhatsapp;
        $conexion->ultimo_latido_en = null;

        $this->assertNull($conexion->ultimo_latido_en);
        $this->assertNull($conexion->getAttributes()['ultimo_latido_en']);
    }
}
