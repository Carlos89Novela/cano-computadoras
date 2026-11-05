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

class EntregarOrdenServicio
{
    public function ejecutar(
        OrdenServicio $orden,
        User $usuario,
        ?string $comentario = null
    ): OrdenServicio {
        if (! $usuario->can('ordenes.actualizar')) {
            throw new AuthorizationException(
                'No tienes permiso para entregar esta reparación.'
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
                $usuario,
                $comentario
            ): OrdenServicio {
                $ordenBloqueada = OrdenServicio::query()
                    ->lockForUpdate()
                    ->findOrFail($orden->id);

                if (
                    $ordenBloqueada->estado !==
                    EstadoOrden::LISTO_PARA_ENTREGA->value
                ) {
                    throw new RuntimeException(
                        'Solo una reparación lista para entrega puede marcarse como entregada.'
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
                        'El presupuesto no fue autorizado por el cliente.'
                    );
                }

                if ($ordenBloqueada->costo_final === null) {
                    throw new RuntimeException(
                        'Debes registrar el costo final antes de entregar el equipo.'
                    );
                }

                if ($ordenBloqueada->fecha_entrega !== null) {
                    throw new RuntimeException(
                        'La reparación ya fue entregada.'
                    );
                }

                $ordenBloqueada->update([
                    'estado' => EstadoOrden::ENTREGADO->value,
                    'fecha_entrega' => now()->toDateString(),
                ]);

                $ordenBloqueada
                    ->asignaciones()
                    ->where('activo', true)
                    ->lockForUpdate()
                    ->get()
                    ->each(function ($asignacion): void {
                        $asignacion->update([
                            'activo' => false,
                            'finalizado_at' => $asignacion->finalizado_at
                                    ?? now(),
                        ]);
                    });

                $ordenBloqueada
                    ->historial()
                    ->create([
                        'user_id' => $usuario->id,
                        'estado' => EstadoOrden::ENTREGADO->value,
                        'comentarios' => filled($comentario)
                            ? $comentario
                            : 'El equipo fue entregado al cliente.',
                        'mensaje_cliente' => 'Tu equipo fue entregado. Gracias por confiar en Cano Computadoras.',
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
                    'Tu equipo fue entregado. Gracias por confiar en Cano Computadoras.'
                )
            );
        }

        return $ordenActualizada;
    }
}
