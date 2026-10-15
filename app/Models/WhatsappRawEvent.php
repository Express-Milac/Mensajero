<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappRawEvent extends Model
{
    protected $table = 'mensajero_whatsapp_raw_events';
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'received_at' => 'immutable_datetime',
            'processed_at' => 'immutable_datetime',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(WhatsappConnection::class, 'connection_id');
    }

    public function chat(): BelongsTo
    {
        return $this->belongsTo(MonitoredChat::class, 'monitored_chat_id');
    }
}
