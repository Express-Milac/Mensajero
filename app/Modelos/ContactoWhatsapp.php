<?php

namespace App\Modelos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactoWhatsapp extends Model
{
    public const CREATED_AT = 'creado_en';
    public const UPDATED_AT = 'actualizado_en';

    protected $table = 'mensajero_contactos_whatsapp';
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'primera_vez_visto_en' => 'immutable_datetime',
            'ultima_vez_visto_en' => 'immutable_datetime',
        ];
    }

    public function conexion(): BelongsTo
    {
        return $this->belongsTo(ConexionWhatsapp::class, 'conexion_id');
    }
}
