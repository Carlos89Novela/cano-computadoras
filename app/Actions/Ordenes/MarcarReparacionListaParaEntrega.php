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

class MarcarReparacionListaParaEntrega
{
    public function ejecutar(
        OrdenServicio $orden,
        User $empleado,
        float $costoFinal,
        ?string $comentario = null
    ): OrdenServicio {
        if (! $empleado->can('ordenes.registrar_avance')) {
            throw new AuthorizationException(
                'No tienes permiso para finalizar el trabajo técnico.'
            );
        }

        if ($costoFinal < 0 || $costoFinal > 99999999.99) {
            throw new RuntimeException(
                'El costo final no es válido.'
            );
        }

        $comentario = is_string($comentario)
            ? trim($comentario)
            : null;

        if (
            $comentario !== null
            && mb_strlen($comentario) > 2000
        ) {
            throw new RuntimeException(
                'El comentario no puede superar los 2000 caracteres.'
            );
        }

        $ordenActualizada = DB::transaction(
            function () use (
                $orden,
                $empleado,
                $costoFinal,
                $comentario
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
                    EstadoOrden::EN_PRUEBAS->value
                ) {
                    throw new RuntimeException(
                        'Solo una reparación en pruebas puede marcarse como lista.'
                    );
                }

                $ordenBloqueada->update([
                    'estado' => EstadoOrden::LISTO_PARA_ENTREGA->value,
                    'costo_final' => round(
                        $costoFinal,
                        2
                    ),
                ]);

                $asignacionActiva->update([
                    'activo' => false,
                    'finalizado_at' => now(),
                ]);

                $ordenBloqueada
                    ->historial()
                    ->create([
                        'user_id' => $empleado->id,
                        'estado' => EstadoOrden::LISTO_PARA_ENTREGA->value,
                        'comentarios' => filled($comentario)
                            ? $comentario
                            : 'Las pruebas finalizaron correctamente.',
                        'mensaje_cliente' => 'Tu equipo está listo para entrega.',
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
                    'Tu equipo está listo para entrega.'
                )
            );
        }

        return $ordenActualizada;
    }
}
