<?php

namespace App\Actions\Ordenes;

use App\Enums\EstadoOrden;
use App\Enums\EstadoRevisionCotizacion;
use App\Models\OrdenServicio;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SolicitarRevisionCotizacion
{
    public function ejecutar(
        OrdenServicio $orden,
        User $empleado
    ): OrdenServicio {
        if (
            ! $empleado->can(
                'ordenes.solicitar_revision_cotizacion'
            )
        ) {
            throw new AuthorizationException(
                'No tienes permiso para solicitar esta revision.'
            );
        }

        return DB::transaction(function () use (
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
                    'La reparacion ya no esta asignada a este empleado.'
                );
            }

            if (
                in_array(
                    $ordenBloqueada->estado,
                    EstadoOrden::finalizados(),
                    true
                )
            ) {
                throw new RuntimeException(
                    'No se puede solicitar la revision de una reparacion finalizada.'
                );
            }

            if (blank($ordenBloqueada->diagnostico)) {
                throw new RuntimeException(
                    'Debes registrar el diagnostico antes de solicitar la revision.'
                );
            }

            if ($ordenBloqueada->costo_estimado === null) {
                throw new RuntimeException(
                    'Debes registrar el costo estimado antes de solicitar la revision.'
                );
            }

            $estadoRevision = $ordenBloqueada
                ->estado_revision_cotizacion;

            $puedeSolicitar = in_array(
                $estadoRevision,
                [
                    EstadoRevisionCotizacion::SIN_SOLICITAR,
                    EstadoRevisionCotizacion::RECHAZADA,
                ],
                true
            );

            if (! $puedeSolicitar) {
                throw new RuntimeException(
                    'La cotizacion no puede enviarse nuevamente a revision.'
                );
            }

            $ordenBloqueada->update([
                'estado_revision_cotizacion' => EstadoRevisionCotizacion::PENDIENTE,
                'cotizacion_revisada_por_id' => null,
                'cotizacion_revisada_at' => null,
                'observacion_revision_cotizacion' => null,
            ]);

            $ordenBloqueada
                ->historial()
                ->create([
                    'user_id' => $empleado->id,
                    'estado' => $ordenBloqueada->estado,
                    'comentarios' => 'Cotizacion enviada a revision del supervisor.',
                    'mensaje_cliente' => null,
                ]);

            return $ordenBloqueada->refresh();
        });
    }
}
