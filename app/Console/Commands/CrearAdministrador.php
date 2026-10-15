<?php

namespace App\Console\Commands;

use App\Enums\Rol;
use App\Models\Usuario;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CrearAdministrador extends Command
{
    protected $signature = 'mensajero:crear-administrador';

    protected $description = 'Crea un administrador sin contraseñas predeterminadas';

    public function handle(): int
    {
        $datos = [
            'name' => $this->ask('Nombre'),
            'email' => mb_strtolower(trim((string) $this->ask('Correo'))),
            'password' => $this->secret('Contraseña (mínimo 12 caracteres)'),
            'password_confirmation' => $this->secret('Confirmar contraseña'),
        ];

        $validador = Validator::make($datos, [
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:254', Rule::unique(Usuario::class, 'email')],
            'password' => 'required|string|min:12|max:1024|confirmed',
        ]);

        if ($validador->fails()) {
            foreach ($validador->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $usuario = new Usuario;
        $usuario->fill(collect($datos)->only(['name', 'email', 'password'])->all());
        $usuario->rol = Rol::Administrador;
        $usuario->save();
        $this->info('Administrador creado.');

        return self::SUCCESS;
    }
}
