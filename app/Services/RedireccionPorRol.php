<?php

namespace App\Services;

use App\Models\User;

class RedireccionPorRol
{
    public function ruta(User $usuario): string
    {
        if ($usuario->hasRole('administrador')) {
            return route(
                'admin.dashboard',
                absolute: false
            );
        }

        if ($usuario->hasRole('supervisor')) {
            return route(
                'supervisor.dashboard',
                absolute: false
            );
        }

        if ($usuario->hasRole('empleado')) {
            return route(
                'empleado.dashboard',
                absolute: false
            );
        }

        return route(
            'dashboard',
            absolute: false
        );
    }
}
