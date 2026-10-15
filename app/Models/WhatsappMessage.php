<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappMessage extends Model
{
    protected $table = 'mensajero_whatsapp_messages';
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'sent_at' => 'immutable_datetime',
            'received_at' => 'immutable_datetime',
            'processed_at' => 'immutable_datetime',
        ];
    }

    public function chat(): BelongsTo
    {
        return $this->belongsTo(MonitoredChat::class, 'monitored_chat_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(WhatsappContact::class, 'sender_contact_id');
    }

    public function rawEvent(): BelongsTo
    {
        return $this->belongsTo(WhatsappRawEvent::class, 'raw_event_id');
    }

    public function quotedMessage(): BelongsTo
    {
        return $this->belongsTo(self::class, 'quoted_message_id');
    }
}
