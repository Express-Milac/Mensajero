<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditoriaMonitoreoChat extends Model
{
    public $timestamps = false;

    protected $table = 'mensajero_auditoria_monitoreo_chats';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'version_autorizacion' => 'integer',
            'es_monitoreado_anterior' => 'boolean',
            'es_monitoreado_nuevo' => 'boolean',
            'cambiado_en' => 'immutable_datetime',
        ];
    }

    public function chat(): BelongsTo
    {
        return $this->belongsTo(ChatMonitoreado::class, 'chat_monitoreado_id');
    }
}
