<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateAdmin extends Command
{
    protected $signature = 'mensajero:create-admin';

    protected $description = 'Crea un administrador sin contraseñas predeterminadas';

    public function handle(): int
    {
        $data = [
            'name' => $this->ask('Nombre'),
            'email' => mb_strtolower(trim((string) $this->ask('Correo'))),
            'password' => $this->secret('Contraseña (mínimo 12 caracteres)'),
            'password_confirmation' => $this->secret('Confirmar contraseña'),
        ];
        $validator = Validator::make($data, [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:254|unique:users,email',
            'password' => 'required|string|min:12|max:1024|confirmed',
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }
        $user = new User;
        $user->fill(collect($data)->only(['name', 'email', 'password'])->all());
        $user->role = Role::Administrator;
        $user->save();
        $this->info('Administrador creado.');

        return self::SUCCESS;
    }
}
