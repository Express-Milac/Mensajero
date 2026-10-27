<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArchivoWhatsapp extends Model
{
    public const CREATED_AT = 'creado_en';

    public const UPDATED_AT = 'actualizado_en';

    protected $table = 'mensajero_archivos_whatsapp';

    protected $guarded = [];

    protected $casts = [
            'tamano_bytes' => 'integer',
        ];

    public function conexion(): BelongsTo
    {
        return $this->belongsTo(ConexionWhatsapp::class, 'conexion_id');
    }

    public function chat(): BelongsTo
    {
        return $this->belongsTo(ChatMonitoreado::class, 'chat_monitoreado_id');
    }

    public function eventoCrudo(): BelongsTo
    {
        return $this->belongsTo(EventoCrudoWhatsapp::class, 'evento_crudo_id');
    }
}
