<?php

namespace Tests\Unit;

use App\Enums\DisposicionEntrada;
use App\Models\ConversacionMonitoreada;
use App\Services\Capture\FiltroMensajeEntrante;
use PHPUnit\Framework\TestCase;

class FiltroMensajeEntranteTest extends TestCase
{
    private FiltroMensajeEntrante $filtro;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filtro = new FiltroMensajeEntrante;
    }

    public function test_conversacion_monitoreada_y_activa_es_aceptada(): void
    {
        $conversacion = new ConversacionMonitoreada(['esta_monitoreada' => true, 'esta_activa' => true]);
        $this->assertSame(DisposicionEntrada::Aceptar, $this->filtro->decidir($conversacion, 'texto'));
    }

    public function test_conversacion_no_monitoreada_se_ignora_por_defecto(): void
    {
        $conversacion = new ConversacionMonitoreada(['esta_monitoreada' => false, 'esta_activa' => true]);
        $this->assertSame(DisposicionEntrada::IgnorarNoMonitoreada, $this->filtro->decidir($conversacion, 'texto'));
    }

    public function test_conversacion_desconocida_se_ignora(): void
    {
        $this->assertSame(DisposicionEntrada::IgnorarConversacionDesconocida, $this->filtro->decidir(null, 'texto'));
    }

    public function test_conversacion_inactiva_se_ignora_aunque_este_monitoreada(): void
    {
        $conversacion = new ConversacionMonitoreada(['esta_monitoreada' => true, 'esta_activa' => false]);
        $this->assertSame(DisposicionEntrada::IgnorarConversacionInactiva, $this->filtro->decidir($conversacion, 'texto'));
    }

    public function test_adhesivo_se_ignora_siempre(): void
    {
        $conversacion = new ConversacionMonitoreada(['esta_monitoreada' => true, 'esta_activa' => true]);
        $this->assertSame(DisposicionEntrada::IgnorarAdhesivo, $this->filtro->decidir($conversacion, 'STICKER'));
        $this->assertSame(DisposicionEntrada::IgnorarAdhesivo, $this->filtro->decidir($conversacion, 'adhesivo'));
    }

    public function test_tipo_desconocido_se_conserva_si_la_conversacion_esta_autorizada(): void
    {
        $conversacion = new ConversacionMonitoreada(['esta_monitoreada' => true, 'esta_activa' => true]);
        $this->assertSame(DisposicionEntrada::Aceptar, $this->filtro->decidir($conversacion, 'tipo_futuro_whatsapp'));
    }
}
