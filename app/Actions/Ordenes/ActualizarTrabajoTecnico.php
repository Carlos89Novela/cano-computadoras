<?php

namespace App\Actions\Ordenes;

use App\Enums\EstadoOrden;
use App\Models\OrdenServicio;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class ActualizarTrabajoTecnico
{
    public function ejecutar(
        OrdenServicio $orden,
        User $empleado,
        array $datos
    ): OrdenServicio {
        return DB::transaction(function () use (
            $orden,
            $empleado,
            $datos
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
                in_array(
                    $ordenBloqueada->estado,
                    EstadoOrden::finalizados(),
                    true
                )
            ) {
                throw new AuthorizationException(
                    'No se puede modificar una reparación finalizada.'
                );
            }

            $actualizaciones = [];

            if (
                array_key_exists('diagnostico', $datos)
                && $ordenBloqueada->diagnostico
                    !== $datos['diagnostico']
            ) {
                $actualizaciones['diagnostico'] =
                    $datos['diagnostico'];
            }

            if (
                array_key_exists('costo_estimado', $datos)
                && $this->costoCambio(
                    $ordenBloqueada->costo_estimado,
                    $datos['costo_estimado']
                )
            ) {
                $actualizaciones['costo_estimado'] =
                    $datos['costo_estimado'];
            }

            $comentario = $datos['comentario'] ?? null;
            $tieneComentario = filled($comentario);

            if ($actualizaciones !== []) {
                $ordenBloqueada->update($actualizaciones);
            }

            if ($actualizaciones !== [] || $tieneComentario) {
                $ordenBloqueada
                    ->historial()
                    ->create([
                        'user_id' => $empleado->id,
                        'estado' => $ordenBloqueada->estado,
                        'comentarios' => $tieneComentario
                            ? $comentario
                            : 'Información técnica actualizada por el empleado.',
                        'mensaje_cliente' => null,
                    ]);
            }

            return $ordenBloqueada->refresh();
        });
    }

    private function costoCambio(
        mixed $costoActual,
        mixed $costoNuevo
    ): bool {
        if ($costoActual === null && $costoNuevo === null) {
            return false;
        }

        if ($costoActual === null || $costoNuevo === null) {
            return true;
        }

        return round((float) $costoActual, 2)
            !== round((float) $costoNuevo, 2);
    }
}
