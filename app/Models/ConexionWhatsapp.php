<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConexionWhatsapp extends Model
{
    public const CREATED_AT = 'creado_en';
    public const UPDATED_AT = 'actualizado_en';

    protected $table = 'mensajero_conexiones_whatsapp';
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'conectado_en' => 'immutable_datetime',
            'ultima_senal_en' => 'immutable_datetime',
            'ultima_desconexion_en' => 'immutable_datetime',
        ];
    }

    public function conversaciones(): HasMany
    {
        return $this->hasMany(ConversacionMonitoreada::class, 'conexion_id');
    }
}
