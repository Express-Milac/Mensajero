<?php

namespace Tests\Unidad;

use App\Enums\DisposicionEntrada;
use App\Models\ChatMonitoreado;
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

    public function test_chat_monitoreado_y_activo_es_aceptado(): void
    {
        $chat = new ChatMonitoreado(['es_monitoreado' => true, 'es_activo' => true]);
        $this->assertSame(DisposicionEntrada::Aceptar, $this->filtro->decidir($chat, 'texto'));
    }

    public function test_chat_no_monitoreado_se_rechaza_por_defecto(): void
    {
        $chat = new ChatMonitoreado(['es_monitoreado' => false, 'es_activo' => true]);
        $this->assertSame(DisposicionEntrada::IgnorarNoMonitoreado, $this->filtro->decidir($chat, 'texto'));
    }

    public function test_chat_desconocido_se_rechaza(): void
    {
        $this->assertSame(DisposicionEntrada::IgnorarChatDesconocido, $this->filtro->decidir(null, 'texto'));
    }

    public function test_chat_inactivo_se_rechaza_aunque_este_monitoreado(): void
    {
        $chat = new ChatMonitoreado(['es_monitoreado' => true, 'es_activo' => false]);
        $this->assertSame(DisposicionEntrada::IgnorarInactivo, $this->filtro->decidir($chat, 'texto'));
    }

    public function test_pegatina_se_rechaza_siempre_incluso_si_proveedor_envia_sticker(): void
    {
        $chat = new ChatMonitoreado(['es_monitoreado' => true, 'es_activo' => true]);
        $this->assertSame(DisposicionEntrada::IgnorarPegatina, $this->filtro->decidir($chat, 'STICKER'));
        $this->assertSame(DisposicionEntrada::IgnorarPegatina, $this->filtro->decidir($chat, 'pegatina'));
    }

    public function test_tipo_desconocido_se_conserva_si_chat_esta_autorizado(): void
    {
        $chat = new ChatMonitoreado(['es_monitoreado' => true, 'es_activo' => true]);
        $this->assertSame(DisposicionEntrada::Aceptar, $this->filtro->decidir($chat, 'tipo_futuro_whatsapp'));
    }
}
