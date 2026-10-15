<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventoCrudoWhatsapp extends Model
{
    public const CREATED_AT = 'creado_en';
    public const UPDATED_AT = 'actualizado_en';

    protected $table = 'mensajero_eventos_crudos_whatsapp';
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'recibido_en' => 'immutable_datetime',
            'procesado_en' => 'immutable_datetime',
        ];
    }

    public function conexion(): BelongsTo
    {
        return $this->belongsTo(ConexionWhatsapp::class, 'conexion_id');
    }

    public function conversacion(): BelongsTo
    {
        return $this->belongsTo(ConversacionMonitoreada::class, 'conversacion_monitoreada_id');
    }
}
