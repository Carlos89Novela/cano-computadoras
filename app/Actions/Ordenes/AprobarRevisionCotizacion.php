<?php

namespace App\Actions\Ordenes;

use App\Enums\EstadoRevisionCotizacion;
use App\Models\OrdenServicio;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AprobarRevisionCotizacion
{
    public function ejecutar(
        OrdenServicio $orden,
        User $revisor
    ): OrdenServicio {
        if (
            ! $revisor->can(
                'ordenes.aprobar_cotizacion'
            )
        ) {
            throw new AuthorizationException(
                'No tienes permiso para aprobar esta cotizacion.'
            );
        }

        return DB::transaction(function () use (
            $orden,
            $revisor
        ): OrdenServicio {
            $ordenBloqueada = OrdenServicio::query()
                ->lockForUpdate()
                ->findOrFail($orden->id);

            if (
                $ordenBloqueada->estado_revision_cotizacion
                !== EstadoRevisionCotizacion::PENDIENTE
            ) {
                throw new RuntimeException(
                    'Solo se pueden aprobar cotizaciones pendientes de revision.'
                );
            }

            if (blank($ordenBloqueada->diagnostico)) {
                throw new RuntimeException(
                    'La cotizacion no tiene un diagnostico registrado.'
                );
            }

            if ($ordenBloqueada->costo_estimado === null) {
                throw new RuntimeException(
                    'La cotizacion no tiene un costo estimado registrado.'
                );
            }

            $ordenBloqueada->update([
                'estado_revision_cotizacion' => EstadoRevisionCotizacion::APROBADA,
                'cotizacion_revisada_por_id' => $revisor->id,
                'cotizacion_revisada_at' => now(),
                'observacion_revision_cotizacion' => null,
            ]);

            $ordenBloqueada
                ->historial()
                ->create([
                    'user_id' => $revisor->id,
                    'estado' => $ordenBloqueada->estado,
                    'comentarios' => 'Cotizacion aprobada por supervision.',
                    'mensaje_cliente' => null,
                ]);

            return $ordenBloqueada->refresh();
        });
    }
}
