<?php

namespace App\Models;

use App\Casts\FechaUtc;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConexionWhatsapp extends Model
{
    public const CREATED_AT = 'creado_en';

    public const UPDATED_AT = 'actualizado_en';

    protected $table = 'mensajero_conexiones_whatsapp';

    protected $guarded = [];

    protected $casts = [
            'conectado_en' => FechaUtc::class,
            'ultimo_latido_en' => FechaUtc::class,
            'ultima_desconexion_en' => FechaUtc::class,
            'metadatos' => 'array',
        ];

    public function chats(): HasMany
    {
        return $this->hasMany(ChatMonitoreado::class, 'conexion_id');
    }
}
