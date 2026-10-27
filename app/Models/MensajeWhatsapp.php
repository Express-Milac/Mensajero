<?php

namespace App\Models;

use App\Casts\FechaUtc;
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
            'enviado_en' => FechaUtc::class,
            'recibido_en' => FechaUtc::class,
            'procesado_en' => FechaUtc::class,
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
