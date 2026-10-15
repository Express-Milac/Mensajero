<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsappConnection extends Model
{
    protected $table = 'mensajero_whatsapp_connections';
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'connected_at' => 'immutable_datetime',
            'last_heartbeat_at' => 'immutable_datetime',
            'last_disconnected_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    public function chats(): HasMany
    {
        return $this->hasMany(MonitoredChat::class, 'connection_id');
    }
}
