<?php

namespace App\Services\Capture;

use App\Data\WhatsApp\CaptureResult;
use App\Data\WhatsApp\IncomingWhatsappEvent;
use App\Enums\InboundDisposition;
use App\Models\MonitoredChat;
use App\Models\OutboxMessage;
use App\Models\WhatsappRawEvent;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class CaptureIncomingWhatsappEvent
{
    public function __construct(
        private readonly InboundMessageGate $gate,
    ) {
    }

    public function handle(IncomingWhatsappEvent $event): CaptureResult
    {
        $deduplicationKey = $event->deduplicationKey();

        try {
            return DB::transaction(function () use ($event, $deduplicationKey): CaptureResult {
                $chat = MonitoredChat::query()
                    ->where('connection_id', $event->connectionId)
                    ->where('provider_chat_id', $event->chatId)
                    ->lockForUpdate()
                    ->first();

                $disposition = $this->gate->decide($chat, $event->messageType);

                if ($disposition !== InboundDisposition::Accept) {
                    return CaptureResult::ignored($disposition);
                }

                $existing = WhatsappRawEvent::query()
                    ->where('connection_id', $event->connectionId)
                    ->where('deduplication_key', $deduplicationKey)
                    ->first();

                if ($existing !== null) {
                    return CaptureResult::duplicate((int) $existing->getKey());
                }

                $raw = WhatsappRawEvent::query()->create([
                    'connection_id' => $event->connectionId,
                    'monitored_chat_id' => $chat->getKey(),
                    'external_event_id' => $event->externalEventId,
                    'deduplication_key' => $deduplicationKey,
                    'event_type' => $event->eventType,
                    'payload' => $event->rawPayload,
                    'payload_sha256' => $event->payloadSha256(),
                    'contract_version' => $event->contractVersion,
                    'processing_status' => 'captured',
                    'attempts' => 0,
                    'received_at' => $event->receivedAt,
                ]);

                OutboxMessage::query()->create([
                    'topic' => 'whatsapp.raw.accepted',
                    'aggregate_type' => 'whatsapp_raw_event',
                    'aggregate_id' => $raw->getKey(),
                    'deduplication_key' => $deduplicationKey,
                    'payload' => json_encode([
                        'raw_event_id' => $raw->getKey(),
                        'connection_id' => $event->connectionId,
                        'monitored_chat_id' => $chat->getKey(),
                    ], JSON_THROW_ON_ERROR),
                    'attempts' => 0,
                    'available_at' => now('UTC'),
                    'created_at' => now('UTC'),
                ]);

                return CaptureResult::accepted((int) $raw->getKey());
            }, 3);
        } catch (QueryException $exception) {
            if (! $this->isSqlServerDuplicateKey($exception)) {
                throw $exception;
            }

            $existing = WhatsappRawEvent::query()
                ->where('connection_id', $event->connectionId)
                ->where('deduplication_key', $deduplicationKey)
                ->first();

            if ($existing === null) {
                throw $exception;
            }

            return CaptureResult::duplicate((int) $existing->getKey());
        }
    }

    private function isSqlServerDuplicateKey(QueryException $exception): bool
    {
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);

        return in_array($driverCode, [2601, 2627], true);
    }
}
