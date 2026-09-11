<?php

namespace App\Policies;

use App\Enums\EstadoAutorizacion;
use App\Enums\EstadoOrden;
use App\Enums\EstadoRevisionCotizacion;
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

    public function viewAssigned(
        User $user,
        OrdenServicio $orden
    ): bool {
        if (! $user->can('ordenes.ver_asignadas')) {
            return false;
        }

        return $orden
            ->asignaciones()
            ->where('empleado_id', $user->id)
            ->where('activo', true)
            ->exists();
    }

    public function updateTechnical(
        User $user,
        OrdenServicio $orden
    ): bool {
        if (! $this->viewAssigned($user, $orden)) {
            return false;
        }

        if (
            ! $user->can('ordenes.registrar_diagnostico')
            || ! $user->can('ordenes.registrar_avance')
            || ! $user->can('ordenes.actualizar_costos')
        ) {
            return false;
        }

        return ! in_array(
            $orden->estado,
            EstadoOrden::finalizados(),
            true
        );
    }

    public function requestQuoteReview(
        User $user,
        OrdenServicio $orden
    ): bool {
        if (! $this->viewAssigned($user, $orden)) {
            return false;
        }

        if (
            ! $user->can(
                'ordenes.solicitar_revision_cotizacion'
            )
        ) {
            return false;
        }

        if (
            in_array(
                $orden->estado,
                EstadoOrden::finalizados(),
                true
            )
        ) {
            return false;
        }

        return in_array(
            $orden->estado_revision_cotizacion,
            [
                EstadoRevisionCotizacion::SIN_SOLICITAR,
                EstadoRevisionCotizacion::RECHAZADA,
            ],
            true
        );
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
