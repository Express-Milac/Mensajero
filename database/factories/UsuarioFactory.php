<?php

namespace Database\Factories;

use App\Enumeraciones\Rol;
use App\Modelos\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** @extends Factory<Usuario> */
class UsuarioFactory extends Factory
{
    protected $model = Usuario::class;

    public function definition(): array
    {
        return [
            'nombre' => fake()->name(),
            'correo' => fake()->unique()->safeEmail(),
            'correo_verificado_en' => now(),
            'clave' => Hash::make('clave-de-prueba-segura'),
            'rol' => Rol::Visualizador,
            'recordar_token' => Str::random(10),
        ];
    }
}
