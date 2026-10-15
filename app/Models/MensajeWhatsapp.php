<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MensajeWhatsapp extends Model
{
    public const CREATED_AT = 'creado_en';
    public const UPDATED_AT = 'actualizado_en';

    protected $table = 'mensajero_mensajes_whatsapp';
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'enviado_en' => 'immutable_datetime',
            'recibido_en' => 'immutable_datetime',
            'procesado_en' => 'immutable_datetime',
        ];
    }

    public function chat(): BelongsTo
    {
        return $this->belongsTo(ChatMonitoreado::class, 'chat_monitoreado_id');
    }

    public function remitente(): BelongsTo
    {
        return $this->belongsTo(ContactoWhatsapp::class, 'contacto_remitente_id');
    }

    public function eventoCrudo(): BelongsTo
    {
        return $this->belongsTo(EventoCrudoWhatsapp::class, 'evento_crudo_id');
    }

    public function mensajeCitado(): BelongsTo
    {
        return $this->belongsTo(self::class, 'mensaje_citado_id');
    }
}
