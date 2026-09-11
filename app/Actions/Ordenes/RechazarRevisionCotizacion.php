<?php

namespace App\Actions\Ordenes;

use App\Enums\EstadoRevisionCotizacion;
use App\Models\OrdenServicio;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class RechazarRevisionCotizacion
{
    public function ejecutar(
        OrdenServicio $orden,
        User $revisor,
        string $observacion
    ): OrdenServicio {
        if (
            ! $revisor->can(
                'ordenes.rechazar_cotizacion'
            )
        ) {
            throw new AuthorizationException(
                'No tienes permiso para rechazar esta cotizacion.'
            );
        }

        $observacion = trim($observacion);

        if ($observacion === '') {
            throw new InvalidArgumentException(
                'Debes indicar el motivo del rechazo.'
            );
        }

        if (mb_strlen($observacion) > 2000) {
            throw new InvalidArgumentException(
                'La observacion no puede superar los 2000 caracteres.'
            );
        }

        return DB::transaction(function () use (
            $orden,
            $revisor,
            $observacion
        ): OrdenServicio {
            $ordenBloqueada = OrdenServicio::query()
                ->lockForUpdate()
                ->findOrFail($orden->id);

            if (
                $ordenBloqueada->estado_revision_cotizacion
                !== EstadoRevisionCotizacion::PENDIENTE
            ) {
                throw new RuntimeException(
                    'Solo se pueden rechazar cotizaciones pendientes de revision.'
                );
            }

            $ordenBloqueada->update([
                'estado_revision_cotizacion' => EstadoRevisionCotizacion::RECHAZADA,
                'cotizacion_revisada_por_id' => $revisor->id,
                'cotizacion_revisada_at' => now(),
                'observacion_revision_cotizacion' => $observacion,
            ]);

            $ordenBloqueada
                ->historial()
                ->create([
                    'user_id' => $revisor->id,
                    'estado' => $ordenBloqueada->estado,
                    'comentarios' => 'Cotizacion devuelta para correccion: '
                        .$observacion,
                    'mensaje_cliente' => null,
                ]);

            return $ordenBloqueada->refresh();
        });
    }
}
