<?php

namespace App\Actions\Ordenes;

use App\Enums\EstadoAutorizacion;
use App\Enums\EstadoOrden;
use App\Enums\EstadoRevisionCotizacion;
use App\Models\OrdenServicio;
use App\Models\User;
use App\Notifications\EstadoReparacionActualizado;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class IniciarReparacionAutorizada
{
    public function ejecutar(
        OrdenServicio $orden,
        User $empleado
    ): OrdenServicio {
        if (! $empleado->can('ordenes.registrar_avance')) {
            throw new AuthorizationException(
                'No tienes permiso para iniciar la reparación.'
            );
        }

        $ordenActualizada = DB::transaction(
            function () use (
                $orden,
                $empleado
            ): OrdenServicio {
                $ordenBloqueada = OrdenServicio::query()
                    ->lockForUpdate()
                    ->findOrFail($orden->id);

                $asignacionActiva = $ordenBloqueada
                    ->asignaciones()
                    ->where('empleado_id', $empleado->id)
                    ->where('activo', true)
                    ->lockForUpdate()
                    ->first();

                if ($asignacionActiva === null) {
                    throw new AuthorizationException(
                        'La reparación ya no está asignada a este empleado.'
                    );
                }

                if (
                    $ordenBloqueada
                        ->estado_revision_cotizacion !==
                    EstadoRevisionCotizacion::APROBADA
                ) {
                    throw new RuntimeException(
                        'La cotización no cuenta con aprobación interna.'
                    );
                }

                if (
                    $ordenBloqueada->autorizacion !==
                    EstadoAutorizacion::AUTORIZADA->value
                ) {
                    throw new RuntimeException(
                        'El cliente no ha autorizado el presupuesto.'
                    );
                }

                if (
                    $ordenBloqueada->estado !==
                    EstadoOrden::ESPERANDO_REFACCION->value
                ) {
                    throw new RuntimeException(
                        'La reparación no está lista para iniciar.'
                    );
                }

                $ordenBloqueada->update([
                    'estado' => EstadoOrden::EN_REPARACION->value,
                ]);

                $ordenBloqueada
                    ->historial()
                    ->create([
                        'user_id' => $empleado->id,
                        'estado' => EstadoOrden::EN_REPARACION->value,
                        'comentarios' => 'El empleado inició la reparación autorizada.',
                        'mensaje_cliente' => 'La reparación autorizada de tu equipo ha comenzado.',
                    ]);

                return $ordenBloqueada->refresh();
            }
        );

        $cliente = $ordenActualizada
            ->user()
            ->first();

        if ($cliente !== null) {
            $cliente->notify(
                new EstadoReparacionActualizado(
                    $ordenActualizada,
                    'La reparación autorizada de tu equipo ha comenzado.'
                )
            );
        }

        return $ordenActualizada;
    }
}
