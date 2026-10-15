<?php

namespace App\Enums;

enum InboundDisposition: string
{
    case Accept = 'accept';
    case IgnoreSticker = 'ignore_sticker';
    case IgnoreUnknownChat = 'ignore_unknown_chat';
    case IgnoreNotMonitored = 'ignore_not_monitored';
    case IgnoreInactive = 'ignore_inactive';
}
