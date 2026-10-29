<?php

namespace App\Models;

use App\Casts\FechaUtc;
use Illuminate\Database\Eloquent\Model;

class MensajeBuzonSalida extends Model
{
    public $timestamps = false;

    protected $table = 'mensajero_buzon_salida';

    protected $guarded = [];

    protected $casts = [
            'disponible_en' => FechaUtc::class,
            'reservado_hasta' => FechaUtc::class,
            'enviado_en' => FechaUtc::class,
            'creado_en' => FechaUtc::class,
        ];
}
