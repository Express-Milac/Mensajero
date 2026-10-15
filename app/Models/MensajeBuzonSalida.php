<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MensajeBuzonSalida extends Model
{
    public $timestamps = false;

    protected $table = 'mensajero_buzon_salida';
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'disponible_en' => 'immutable_datetime',
            'reservado_hasta' => 'immutable_datetime',
            'enviado_en' => 'immutable_datetime',
            'creado_en' => 'immutable_datetime',
        ];
    }
}
