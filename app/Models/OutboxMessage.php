<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutboxMessage extends Model
{
    protected $table = 'mensajero_outbox';
    public $timestamps = false;
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'available_at' => 'immutable_datetime',
            'lease_until' => 'immutable_datetime',
            'dispatched_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
