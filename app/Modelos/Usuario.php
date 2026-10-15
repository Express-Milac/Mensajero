<?php

namespace App\Modelos;

use App\Enumeraciones\Rol;
use Database\Factories\UsuarioFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['nombre', 'correo', 'clave'])]
#[Hidden(['clave', 'recordar_token'])]
class Usuario extends Authenticatable
{
    /** @use HasFactory<UsuarioFactory> */
    use HasFactory, Notifiable;

    public const CREATED_AT = 'creado_en';
    public const UPDATED_AT = 'actualizado_en';

    protected $table = 'mensajero_usuarios';

    public function getAuthPasswordName(): string
    {
        return 'clave';
    }

    public function getRememberTokenName(): string
    {
        return 'recordar_token';
    }

    protected function casts(): array
    {
        return [
            'rol' => Rol::class,
            'correo_verificado_en' => 'datetime',
            'clave' => 'hashed',
        ];
    }
}
