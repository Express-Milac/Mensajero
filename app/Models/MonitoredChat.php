<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MonitoredChat extends Model
{
    protected $table = 'mensajero_monitored_chats';
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_monitored' => 'boolean',
            'is_active' => 'boolean',
            'first_seen_at' => 'immutable_datetime',
            'last_seen_at' => 'immutable_datetime',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(WhatsappConnection::class, 'connection_id');
    }

    public function rawEvents(): HasMany
    {
        return $this->hasMany(WhatsappRawEvent::class, 'monitored_chat_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsappMessage::class, 'monitored_chat_id');
    }
}
