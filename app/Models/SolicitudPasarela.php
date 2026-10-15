<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitudPasarela extends Model
{
    public $timestamps = false;

    protected $table = 'mensajero_solicitudes_pasarela';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'marca_tiempo_pasarela' => 'integer',
            'recibido_en' => 'immutable_datetime',
        ];
    }
}
