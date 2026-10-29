<?php

namespace App\Models;

use App\Casts\FechaUtc;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PeriodoIntegridadWhatsapp extends Model
{
    public const CREATED_AT = 'creado_en';

    public const UPDATED_AT = 'actualizado_en';

    protected $table = 'mensajero_periodos_integridad_whatsapp';

    protected $guarded = [];

    protected $casts = [
            'inicio_en' => FechaUtc::class,
            'fin_en' => FechaUtc::class,
            'detalles' => 'array',
        ];

    public function conexion(): BelongsTo
    {
        return $this->belongsTo(ConexionWhatsapp::class, 'conexion_id');
    }
}
