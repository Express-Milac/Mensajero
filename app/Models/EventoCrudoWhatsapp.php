<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    public function chat(): BelongsTo
    {
        return $this->belongsTo(ChatMonitoreado::class, 'chat_monitoreado_id');
    }

    public function archivo(): HasOne
    {
        return $this->hasOne(ArchivoWhatsapp::class, 'evento_crudo_id');
    }
}
