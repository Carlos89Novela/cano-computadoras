<?php

namespace App\Policies;

use App\Enums\EstadoAutorizacion;
use App\Enums\EstadoOrden;
use App\Models\OrdenServicio;
use App\Models\User;

class OrdenServicioPolicy
{
    public function view(
        User $user,
        OrdenServicio $orden
    ): bool {
        return $this->esPropietario($user, $orden);
    }

    public function authorizeBudget(
        User $user,
        OrdenServicio $orden
    ): bool {
        return $this->esPropietario($user, $orden)
            && $orden->estado === EstadoOrden::ESPERANDO_AUTORIZACION->value
            && $orden->autorizacion === EstadoAutorizacion::PENDIENTE->value;
    }

    public function downloadPdf(
        User $user,
        OrdenServicio $orden
    ): bool {
        return $this->esPropietario($user, $orden);
    }

    private function esPropietario(
        User $user,
        OrdenServicio $orden
    ): bool {
        return (int) $orden->user_id === (int) $user->id;
    }
}
