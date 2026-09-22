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

class EnviarReparacionAPruebas
{
    public function ejecutar(
        OrdenServicio $orden,
        User $empleado,
        ?string $comentario = null
    ): OrdenServicio {
        if (! $empleado->can('ordenes.registrar_avance')) {
            throw new AuthorizationException(
                'No tienes permiso para registrar avances.'
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
                $comentario
            ): OrdenServicio {
                $ordenBloqueada = OrdenServicio::query()
                    ->lockForUpdate()
                    ->findOrFail($orden->id);

                $asignacionActiva = $ordenBloqueada
                    ->asignaciones()
                    ->where(
                        'empleado_id',
                        $empleado->id
                    )
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
                    EstadoOrden::EN_REPARACION->value
                ) {
                    throw new RuntimeException(
                        'Solo una reparación en proceso puede enviarse a pruebas.'
                    );
                }

                $ordenBloqueada->update([
                    'estado' => EstadoOrden::EN_PRUEBAS->value,
                ]);

                $ordenBloqueada
                    ->historial()
                    ->create([
                        'user_id' => $empleado->id,
                        'estado' => EstadoOrden::EN_PRUEBAS->value,
                        'comentarios' => filled($comentario)
                            ? $comentario
                            : 'La reparación fue enviada a pruebas.',
                        'mensaje_cliente' => 'La reparación de tu equipo se encuentra en pruebas.',
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
                    'La reparación de tu equipo se encuentra en pruebas.'
                )
            );
        }

        return $ordenActualizada;
    }
}
