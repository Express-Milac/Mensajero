<?php

namespace App\Services\Capture;

use App\Enums\InboundDisposition;
use App\Models\MonitoredChat;

final class InboundMessageGate
{
    public function decide(?MonitoredChat $chat, string $messageType): InboundDisposition
    {
        if (mb_strtolower($messageType) === 'sticker') {
            return InboundDisposition::IgnoreSticker;
        }

        if ($chat === null) {
            return InboundDisposition::IgnoreUnknownChat;
        }

        if (! $chat->is_active) {
            return InboundDisposition::IgnoreInactive;
        }

        if (! $chat->is_monitored) {
            return InboundDisposition::IgnoreNotMonitored;
        }

        return InboundDisposition::Accept;
    }
}
