<?php

namespace App\Actions\Ordenes;

use App\Models\OrdenAsignacion;
use App\Models\OrdenServicio;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AsignarOrden
{
    public function ejecutar(
        OrdenServicio $orden,
        User $empleado,
        User $asignadoPor,
        ?string $observaciones = null
    ): OrdenAsignacion {
        if (! $empleado->hasRole('empleado')) {
            throw new InvalidArgumentException(
                'El usuario seleccionado no tiene el rol de empleado.'
            );
        }

        return DB::transaction(function () use (
            $orden,
            $empleado,
            $asignadoPor,
            $observaciones
        ): OrdenAsignacion {
            $ordenBloqueada = OrdenServicio::query()
                ->lockForUpdate()
                ->findOrFail($orden->id);

            $asignacionActual = $ordenBloqueada
                ->asignacionActiva()
                ->lockForUpdate()
                ->first();

            if (
                $asignacionActual !== null
                && $asignacionActual->empleado_id === $empleado->id
            ) {
                return $asignacionActual;
            }

            $permisoNecesario = $asignacionActual === null
                ? 'ordenes.asignar'
                : 'ordenes.reasignar';

            if (! $asignadoPor->can($permisoNecesario)) {
                throw new AuthorizationException(
                    'No tienes permiso para asignar esta reparación.'
                );
            }

            if ($asignacionActual !== null) {
                $asignacionActual->update([
                    'activo' => false,
                    'finalizado_at' => now(),
                ]);
            }

            $nuevaAsignacion = $ordenBloqueada
                ->asignaciones()
                ->create([
                    'empleado_id' => $empleado->id,
                    'asignado_por_id' => $asignadoPor->id,
                    'asignado_at' => now(),
                    'finalizado_at' => null,
                    'activo' => true,
                    'observaciones' => $observaciones,
                ]);

            $ordenBloqueada
                ->historial()
                ->create([
                    'user_id' => $asignadoPor->id,
                    'estado' => $ordenBloqueada->estado,
                    'comentarios' => $asignacionActual === null
                        ? 'Reparación asignada a '.$empleado->name.'.'
                        : 'Reparación reasignada a '.$empleado->name.'.',
                    'mensaje_cliente' => null,
                ]);

            return $nuevaAsignacion;
        });
    }
}
