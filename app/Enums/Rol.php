<?php

namespace App\Enums;

/** Roles persistidos como cadenas en SQL Server, compatibles con PHP 7.4. */
final class Rol
{
    public const Administrador = 'administrador';
    public const Supervisor = 'supervisor';
    public const Visualizador = 'visualizador';

    public static function etiqueta(string $rol): string
    {
        switch ($rol) {
            case self::Administrador:
                return 'Administrador';
            case self::Supervisor:
                return 'Supervisor';
            case self::Visualizador:
                return 'Visualizador';
            default:
                return 'Desconocido';
        }
    }

    private function __construct() {}
}
