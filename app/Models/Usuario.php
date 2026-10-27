<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;

class Usuario extends Authenticatable
{
    use HasFactory, Notifiable;

    public const CREATED_AT = 'creado_en';
    public const UPDATED_AT = 'actualizado_en';

    protected $table = 'mensajero_usuarios';
    protected $fillable = ['nombre', 'email', 'password'];
    protected $hidden = ['password', 'remember_token'];
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /** Sustituye el cast "hashed" de Laravel 13, inexistente en Laravel 8. */
    public function setPasswordAttribute(?string $valor): void
    {
        if ($valor === null) {
            $this->attributes['password'] = null;
            return;
        }

        $this->attributes['password'] = Hash::make($valor);
    }
}
