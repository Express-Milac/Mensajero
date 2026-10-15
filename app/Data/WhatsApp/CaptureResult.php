<?php

namespace App\Data\WhatsApp;

use App\Enums\InboundDisposition;

final readonly class CaptureResult
{
    private function __construct(
        public string $status,
        public InboundDisposition $disposition,
        public ?int $rawEventId = null,
    ) {
    }

    public static function accepted(int $rawEventId): self
    {
        return new self('accepted', InboundDisposition::Accept, $rawEventId);
    }

    public static function duplicate(int $rawEventId): self
    {
        return new self('duplicate', InboundDisposition::Accept, $rawEventId);
    }

    public static function ignored(InboundDisposition $disposition): self
    {
        return new self('ignored', $disposition);
    }
}
