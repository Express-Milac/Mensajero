<?php

namespace App\Modelos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConversacionMonitoreada extends Model
{
    public const CREATED_AT = 'creado_en';
    public const UPDATED_AT = 'actualizado_en';

    protected $table = 'mensajero_conversaciones_monitoreadas';
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'esta_monitoreada' => 'boolean',
            'esta_activa' => 'boolean',
            'primera_vez_vista_en' => 'immutable_datetime',
            'ultima_vez_vista_en' => 'immutable_datetime',
        ];
    }

    public function conexion(): BelongsTo
    {
        return $this->belongsTo(ConexionWhatsapp::class, 'conexion_id');
    }

    public function eventosCrudos(): HasMany
    {
        return $this->hasMany(EventoCrudoWhatsapp::class, 'conversacion_monitoreada_id');
    }

    public function mensajes(): HasMany
    {
        return $this->hasMany(MensajeWhatsapp::class, 'conversacion_monitoreada_id');
    }
}
