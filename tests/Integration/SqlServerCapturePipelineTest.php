<?php

namespace Tests\Integration;

use App\Data\WhatsApp\IncomingWhatsappEvent;
use App\Enums\InboundDisposition;
use App\Models\MonitoredChat;
use App\Models\OutboxMessage;
use App\Models\WhatsappConnection;
use App\Models\WhatsappRawEvent;
use App\Services\Capture\CaptureIncomingWhatsappEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SqlServerCapturePipelineTest extends TestCase
{
    public function test_allowlist_sticker_and_idempotency_before_raw(): void
    {
        $this->assertStringEndsWith('_test', config('database.connections.sqlsrv.database'), 'Use a disposable database ending in _test.');
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();

        DB::beginTransaction();
        try {
            $connection = WhatsappConnection::create([
                'connection_key' => 'pipeline-primary',
                'provider' => 'self_hosted_web',
                'status' => 'connected',
            ]);
            $chat = MonitoredChat::create([
                'connection_id' => $connection->id,
                'provider_chat_id' => 'operation-test@g.us',
                'chat_type' => 'group',
            ]);
            $capture = app(CaptureIncomingWhatsappEvent::class);
            $event = new IncomingWhatsappEvent(
                connectionId: (int) $connection->id,
                chatId: $chat->provider_chat_id,
                eventType: 'message',
                messageType: 'text',
                externalEventId: 'evt-001',
                providerMessageId: 'msg-001',
                rawPayload: '{"id":"evt-001","text":"Unidad lista"}',
                receivedAt: CarbonImmutable::parse('2026-10-15T18:00:00Z'),
            );

            $ignored = $capture->handle($event);
            $this->assertSame('ignored', $ignored->status);
            $this->assertSame(InboundDisposition::IgnoreNotMonitored, $ignored->disposition);
            $this->assertSame(0, WhatsappRawEvent::count());
            $this->assertSame(0, OutboxMessage::count());

            $chat->update(['is_monitored' => true]);
            $accepted = $capture->handle($event);
            $this->assertSame('accepted', $accepted->status);
            $this->assertSame(1, WhatsappRawEvent::count());
            $this->assertSame(1, OutboxMessage::count());

            $duplicate = $capture->handle($event);
            $this->assertSame('duplicate', $duplicate->status);
            $this->assertSame($accepted->rawEventId, $duplicate->rawEventId);
            $this->assertSame(1, WhatsappRawEvent::count());
            $this->assertSame(1, OutboxMessage::count());

            $sticker = new IncomingWhatsappEvent(
                connectionId: (int) $connection->id,
                chatId: $chat->provider_chat_id,
                eventType: 'message',
                messageType: 'sticker',
                externalEventId: 'evt-002',
                providerMessageId: 'msg-002',
                rawPayload: '{"id":"evt-002","type":"sticker"}',
                receivedAt: CarbonImmutable::parse('2026-10-15T18:01:00Z'),
            );
            $ignoredSticker = $capture->handle($sticker);
            $this->assertSame(InboundDisposition::IgnoreSticker, $ignoredSticker->disposition);
            $this->assertSame(1, WhatsappRawEvent::count());
            $this->assertSame(1, OutboxMessage::count());
        } finally {
            DB::rollBack();
        }
    }
}
