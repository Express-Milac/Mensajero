<?php

namespace App\Console\Commands;

use App\Enumeraciones\Rol;
use App\Modelos\Usuario;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CrearAdministrador extends Command
{
    protected $signature = 'mensajero:crear-administrador';

    protected $description = 'Crea un administrador local de prueba sin claves predeterminadas';

    public function handle(): int
    {
        $datos = [
            'nombre' => $this->ask('Nombre'),
            'correo' => mb_strtolower(trim((string) $this->ask('Correo'))),
            'clave' => $this->secret('Contraseña (mínimo 12 caracteres)'),
            'confirmacion_clave' => $this->secret('Confirmar contraseña'),
        ];

        $validador = Validator::make($datos, [
            'nombre' => 'required|string|max:255',
            'correo' => 'required|email|max:254|unique:mensajero_usuarios,correo',
            'clave' => 'required|string|min:12|max:1024|same:confirmacion_clave',
        ]);

        if ($validador->fails()) {
            foreach ($validador->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $usuario = new Usuario;
        $usuario->fill([
            'nombre' => $datos['nombre'],
            'correo' => $datos['correo'],
            'clave' => $datos['clave'],
        ]);
        $usuario->rol = Rol::Administrador;
        $usuario->save();
        $this->info('Administrador creado.');

        return self::SUCCESS;
    }
}
