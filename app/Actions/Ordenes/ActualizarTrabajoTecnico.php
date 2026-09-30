<?php

namespace App\Actions\Ordenes;

use App\Enums\EstadoOrden;
use App\Models\OrdenServicio;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Acción del Dominio de Órdenes: Actualizar Trabajo Técnico.
 *
 * Permite a un empleado técnico actualizar el diagnóstico preliminar o definitivo
 * y el costo estimado de una orden de servicio que tiene asignada activamente.
 *
 * Garantías de integridad:
 * - Concurrencia segura: Aplica bloqueos pesimistas (`lockForUpdate`) para evitar condiciones de carrera.
 * - Validación de asignación: Requiere que el técnico tenga una asignación activa vigente sobre la orden.
 * - Inmutabilidad terminal: Impide modificaciones sobre órdenes con estatus finalizado (entregadas o canceladas).
 * - Detección de cambios: Registra entradas en el historial interno únicamente si hubo alteraciones reales o comentarios.
 */
class ActualizarTrabajoTecnico
{
    /**
     * Ejecuta la actualización de la información técnica de la orden.
     *
     * @param  OrdenServicio  $orden  Orden de servicio a modificar.
     * @param  User  $empleado  Usuario técnico que ejecuta la acción.
     * @param  array<string, mixed>  $datos  Valores enviados (diagnostico, costo_estimado, comentario).
     * @return OrdenServicio Instancia de la orden actualizada y refrescada.
     *
     * @throws AuthorizationException Si el usuario no tiene la asignación activa o si la orden ya finalizó.
     */
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
            // Bloqueo pesimista para lectura y escritura atómica de la orden
            $ordenBloqueada = OrdenServicio::query()
                ->lockForUpdate()
                ->findOrFail($orden->id);

            // Verifica que el empleado autenticado posea la asignación activa sobre esta orden
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

            // Regla de negocio: Órdenes en estado terminal no pueden modificarse
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

            // Valida y detecta cambios reales en el diagnóstico técnico
            if (
                array_key_exists('diagnostico', $datos)
                && $ordenBloqueada->diagnostico
                    !== $datos['diagnostico']
            ) {
                $actualizaciones['diagnostico'] =
                    $datos['diagnostico'];
            }

            // Valida y detecta cambios monetarios en el costo estimado
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

            // Aplica las modificaciones en el registro de la orden si hubo cambios
            if ($actualizaciones !== []) {
                $ordenBloqueada->update($actualizaciones);
            }

            // Registra en el historial técnico interno si cambiaron datos o se agregó un comentario
            if ($actualizaciones !== [] || $tieneComentario) {
                $ordenBloqueada
                    ->historial()
                    ->create([
                        'user_id' => $empleado->id,
                        'estado' => $ordenBloqueada->estado,
                        'comentarios' => $tieneComentario
                            ? $comentario
                            : 'Información técnica actualizada por el empleado.',
                        'mensaje_cliente' => null, // Nota técnica interna, no visible para el cliente
                    ]);
            }

            return $ordenBloqueada->refresh();
        });
    }

    /**
     * Compara dos valores monetarios considerando redondeo a 2 decimales y estados nulos.
     *
     * @param  mixed  $costoActual  Costo existente en base de datos.
     * @param  mixed  $costoNuevo  Nuevo valor enviado por el técnico.
     * @return bool True si los valores representan montos distintos, false en caso contrario.
     */
    private function costoCambio(
        mixed $costoActual,
        mixed $costoNuevo
    ): bool {
        // Si ambos son nulos, no hay cambio
        if ($costoActual === null && $costoNuevo === null) {
            return false;
        }

        // Si uno es nulo y el otro tiene valor, hubo un cambio
        if ($costoActual === null || $costoNuevo === null) {
            return true;
        }

        // Comparación con redondeo monetario a 2 posiciones decimales
        return round((float) $costoActual, 2)
            !== round((float) $costoNuevo, 2);
    }
}
