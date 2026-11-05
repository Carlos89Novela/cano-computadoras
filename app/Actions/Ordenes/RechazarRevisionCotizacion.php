<?php

namespace App\Actions\Ordenes;

use App\Enums\EstadoRevisionCotizacion;
use App\Models\OrdenServicio;
use App\Models\User;
use App\Notifications\CotizacionRechazadaInternamente;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Acción del Dominio de Órdenes: Rechazar Revisión de Cotización.
 *
 * Permite a un supervisor o administrador rechazar el presupuesto preliminar preparado por el técnico,
 * devolviéndolo con observaciones puntuales para que el técnico corrija el diagnóstico o reestructure los costos.
 *
 * Flujo y consideraciones operativas:
 * 1. Autorización RBAC: Requiere el permiso 'ordenes.rechazar_cotizacion'.
 * 2. Observación obligatoria: La justificación técnica del rechazo no puede estar vacía (máximo 2000 caracteres).
 * 3. Precondición de estado: Solo cotizaciones en estado 'PENDIENTE' pueden ser rechazadas.
 * 4. Transición de estado:
 *    - `estado_revision_cotizacion`: Pasa a 'RECHAZADA'.
 *    - `cotizacion_revisada_por_id`: Se asocia al supervisor actuante.
 *    - `observacion_revision_cotizacion`: Se almacena la retroalimentación.
 * 5. Notificación técnica: Notifica directamente al técnico asignado (`CotizacionRechazadaInternamente`)
 *    para que atienda las correcciones solicitadas.
 */
class RechazarRevisionCotizacion
{
    /**
     * Rechaza la cotización técnica y la devuelve al técnico con observaciones.
     *
     * @param  OrdenServicio  $orden  Orden de servicio en revisión.
     * @param  User  $revisor  Supervisor o administrador que efectúa el rechazo.
     * @param  string  $observacion  Detalle y justificación de las razones del rechazo.
     * @return OrdenServicio Orden actualizada con estado de cotización RECHAZADA.
     *
     * @throws AuthorizationException Si el revisor carece del permiso correspondiente.
     * @throws InvalidArgumentException Si la observación está vacía o excede 2000 caracteres.
     * @throws RuntimeException Si la cotización no se encuentra pendiente de revisión.
     */
    public function ejecutar(
        OrdenServicio $orden,
        User $revisor,
        string $observacion
    ): OrdenServicio {
        // Valida que el revisor posea los permisos de rechazo de presupuestos
        if (
            ! $revisor->can(
                'ordenes.rechazar_cotizacion'
            )
        ) {
            throw new AuthorizationException(
                'No tienes permiso para rechazar esta cotizacion.'
            );
        }

        // Sanitización y validación estricta del motivo del rechazo
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

        // Ejecución atómica de la transición y auditoría interna
        $ordenActualizada = DB::transaction(function () use (
            $orden,
            $revisor,
            $observacion
        ): OrdenServicio {
            $ordenBloqueada = OrdenServicio::query()
                ->lockForUpdate()
                ->findOrFail($orden->id);

            // Valida que la cotización se encuentre pendiente
            if (
                $ordenBloqueada->estado_revision_cotizacion
                !== EstadoRevisionCotizacion::PENDIENTE
            ) {
                throw new RuntimeException(
                    'Solo se pueden rechazar cotizaciones pendientes de revision.'
                );
            }

            // Actualiza a RECHAZADA y graba las observaciones del supervisor
            $ordenBloqueada->update([
                'estado_revision_cotizacion' => EstadoRevisionCotizacion::RECHAZADA,
                'cotizacion_revisada_por_id' => $revisor->id,
                'cotizacion_revisada_at' => now(),
                'observacion_revision_cotizacion' => $observacion,
            ]);

            // Asienta en la bitácora interna de la reparación
            $ordenBloqueada
                ->historial()
                ->create([
                    'user_id' => $revisor->id,
                    'estado' => $ordenBloqueada->estado,
                    'comentarios' => 'Cotizacion devuelta para correccion: '
                        .$observacion,
                    'mensaje_cliente' => null, // Evento interno de supervisión técnica
                ]);

            return $ordenBloqueada->refresh();
        });

        // Notifica al técnico asignado sobre el rechazo y las observaciones emitidas
        $asignacionActiva = $ordenActualizada
            ->asignacionActiva()
            ->with('empleado')
            ->first();

        if ($asignacionActiva !== null) {
            $asignacionActiva->empleado->notify(
                new CotizacionRechazadaInternamente(
                    $ordenActualizada,
                    $observacion
                )
            );
        }

        return $ordenActualizada;
    }
}
