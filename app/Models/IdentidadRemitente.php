<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class IdentidadRemitente extends Model
{
    public const CREATED_AT = 'creado_en';

    public const UPDATED_AT = 'actualizado_en';

    protected $table = 'mensajero_identidades_remitentes';

    protected $guarded = [];
}
