<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PeriodoIntegridadWhatsapp extends Model
{
    public const CREATED_AT = 'creado_en';

    public const UPDATED_AT = 'actualizado_en';

    protected $table = 'mensajero_periodos_integridad_whatsapp';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'inicio_en' => 'immutable_datetime',
            'fin_en' => 'immutable_datetime',
            'detalles' => 'array',
        ];
    }

    public function conexion(): BelongsTo
    {
        return $this->belongsTo(ConexionWhatsapp::class, 'conexion_id');
    }
}
