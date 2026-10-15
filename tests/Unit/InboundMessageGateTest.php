<?php

namespace Tests\Unit;

use App\Enums\InboundDisposition;
use App\Models\MonitoredChat;
use App\Services\Capture\InboundMessageGate;
use PHPUnit\Framework\TestCase;

class InboundMessageGateTest extends TestCase
{
    private InboundMessageGate $gate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gate = new InboundMessageGate;
    }

    public function test_monitored_active_chat_is_accepted(): void
    {
        $chat = new MonitoredChat(['is_monitored' => true, 'is_active' => true]);
        $this->assertSame(InboundDisposition::Accept, $this->gate->decide($chat, 'text'));
    }

    public function test_unmonitored_chat_is_rejected_by_default(): void
    {
        $chat = new MonitoredChat(['is_monitored' => false, 'is_active' => true]);
        $this->assertSame(InboundDisposition::IgnoreNotMonitored, $this->gate->decide($chat, 'text'));
    }

    public function test_unknown_chat_is_rejected(): void
    {
        $this->assertSame(InboundDisposition::IgnoreUnknownChat, $this->gate->decide(null, 'text'));
    }

    public function test_inactive_chat_is_rejected_even_if_monitored(): void
    {
        $chat = new MonitoredChat(['is_monitored' => true, 'is_active' => false]);
        $this->assertSame(InboundDisposition::IgnoreInactive, $this->gate->decide($chat, 'text'));
    }

    public function test_sticker_is_always_rejected(): void
    {
        $chat = new MonitoredChat(['is_monitored' => true, 'is_active' => true]);
        $this->assertSame(InboundDisposition::IgnoreSticker, $this->gate->decide($chat, 'STICKER'));
    }

    public function test_unknown_message_type_is_kept_when_chat_is_authorized(): void
    {
        $chat = new MonitoredChat(['is_monitored' => true, 'is_active' => true]);
        $this->assertSame(InboundDisposition::Accept, $this->gate->decide($chat, 'future_whatsapp_type'));
    }
}
