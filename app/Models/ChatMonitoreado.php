<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatMonitoreado extends Model
{
    public const CREATED_AT = 'creado_en';
    public const UPDATED_AT = 'actualizado_en';

    protected $table = 'mensajero_chats_monitoreados';
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'es_monitoreado' => 'boolean',
            'es_activo' => 'boolean',
            'visto_primera_vez_en' => 'immutable_datetime',
            'visto_ultima_vez_en' => 'immutable_datetime',
        ];
    }

    public function conexion(): BelongsTo
    {
        return $this->belongsTo(ConexionWhatsapp::class, 'conexion_id');
    }

    public function eventosCrudos(): HasMany
    {
        return $this->hasMany(EventoCrudoWhatsapp::class, 'chat_monitoreado_id');
    }

    public function mensajes(): HasMany
    {
        return $this->hasMany(MensajeWhatsapp::class, 'chat_monitoreado_id');
    }
}
