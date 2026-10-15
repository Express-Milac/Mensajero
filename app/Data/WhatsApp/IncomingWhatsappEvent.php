<?php

namespace App\Data\WhatsApp;

use Carbon\CarbonImmutable;

final readonly class IncomingWhatsappEvent
{
    public function __construct(
        public int $connectionId,
        public string $chatId,
        public string $eventType,
        public string $messageType,
        public ?string $externalEventId,
        public ?string $providerMessageId,
        public string $rawPayload,
        public CarbonImmutable $receivedAt,
        public string $contractVersion = 'v1',
    ) {
    }

    public function payloadSha256(): string
    {
        return hash('sha256', $this->rawPayload);
    }

    public function deduplicationKey(): string
    {
        if ($this->externalEventId !== null && $this->externalEventId !== '') {
            return hash('sha256', 'event:'.$this->externalEventId);
        }

        return hash('sha256', implode('|', [
            'fallback',
            (string) $this->connectionId,
            $this->chatId,
            $this->eventType,
            $this->providerMessageId ?? '',
            $this->payloadSha256(),
        ]));
    }
}
