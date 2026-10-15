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
            'ultimo_latido_en' => 'immutable_datetime',
            'ultima_desconexion_en' => 'immutable_datetime',
            'metadatos' => 'array',
        ];
    }

    public function chats(): HasMany
    {
        return $this->hasMany(ChatMonitoreado::class, 'conexion_id');
    }
}
