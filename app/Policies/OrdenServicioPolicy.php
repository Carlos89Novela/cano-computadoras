<?php

namespace App\Policies;

use App\Enums\EstadoAutorizacion;
use App\Enums\EstadoOrden;
use App\Enums\EstadoRevisionCotizacion;
use App\Models\OrdenServicio;
use App\Models\User;

/**
 * Política de Autorización y Reglas de Negocio para Órdenes de Servicio de Taller.
 *
 * Esta clase es el núcleo de seguridad y control del flujo de trabajo de reparación.
 * Aplica reglas estrictas que combinan:
 * 1. Pertenencia de cliente (propiedad de la orden y descarga de comprobantes).
 * 2. Asignación técnica activa (un técnico solo opera órdenes formalmente asignadas a él).
 * 3. Permisos granulares de Spatie (roles de administración, supervisión o técnico).
 * 4. Precondiciones de estado del ciclo de vida (máquina de estados e hitos de cotización).
 */
class OrdenServicioPolicy
{
    /**
     * Determina si un cliente puede consultar el detalle de su orden de servicio.
     *
     * @param  User  $user  Usuario autenticado.
     * @param  OrdenServicio  $orden  Orden de servicio consultada.
     * @return bool Verdadero si el usuario es el cliente dueño de la orden.
     */
    public function view(
        User $user,
        OrdenServicio $orden
    ): bool {
        return $this->esPropietario($user, $orden);
    }

    /**
     * Determina si un empleado técnico puede ver la orden en su lista de trabajo.
     *
     * Exige que el técnico tenga el permiso 'ordenes.ver_asignadas' y que exista
     * una asignación activa vigente vinculada a su cuenta de usuario.
     *
     * @param  User  $user  Empleado técnico.
     * @param  OrdenServicio  $orden  Orden de servicio.
     * @return bool Verdadero si tiene asignación activa y permisos.
     */
    public function viewAssigned(
        User $user,
        OrdenServicio $orden
    ): bool {
        if (! $user->can('ordenes.ver_asignadas')) {
            return false;
        }

        return $orden
            ->asignaciones()
            ->where('empleado_id', $user->id)
            ->where('activo', true)
            ->exists();
    }

    /**
     * Determina si un técnico puede actualizar el diagnóstico, costo estimado y bitácora técnica.
     *
     * Precondiciones:
     * - Debe tener asignación activa.
     * - Debe poseer los permisos de diagnóstico, avance y costos.
     * - La orden no debe encontrarse en un estado terminal (entregado o cancelado).
     *
     * @param  User  $user  Empleado técnico.
     * @param  OrdenServicio  $orden  Orden de servicio.
     * @return bool Verdadero si cumple todos los requisitos.
     */
    public function updateTechnical(
        User $user,
        OrdenServicio $orden
    ): bool {
        if (! $this->viewAssigned($user, $orden)) {
            return false;
        }

        if (
            ! $user->can('ordenes.registrar_diagnostico')
            || ! $user->can('ordenes.registrar_avance')
            || ! $user->can('ordenes.actualizar_costos')
        ) {
            return false;
        }

        return ! in_array(
            $orden->estado,
            EstadoOrden::finalizados(),
            true
        );
    }

    /**
     * Determina si un técnico puede dar inicio a la reparación autorizada en taller.
     *
     * Precondiciones:
     * - Técnico con asignación activa y permiso de avance.
     * - Cotización aprobada por supervisor.
     * - Presupuesto autorizado por el cliente.
     * - Orden en espera de refacciones (etapa previa reglamentaria).
     *
     * @param  User  $user  Empleado técnico.
     * @param  OrdenServicio  $orden  Orden de servicio.
     * @return bool Verdadero si la orden puede comenzar trabajos en mesa.
     */
    public function startAuthorizedRepair(
        User $user,
        OrdenServicio $orden
    ): bool {
        $tieneAsignacionActiva = $orden
            ->asignaciones()
            ->where('empleado_id', $user->id)
            ->where('activo', true)
            ->exists();

        return $user->can('ordenes.registrar_avance')
            && $tieneAsignacionActiva
            && $orden->estado_revision_cotizacion ===
                EstadoRevisionCotizacion::APROBADA
            && $orden->autorizacion ===
                EstadoAutorizacion::AUTORIZADA->value
            && $orden->estado ===
                EstadoOrden::ESPERANDO_REFACCION->value;
    }

    /**
     * Determina si un técnico puede enviar una reparación en curso a pruebas de calidad.
     *
     * Precondiciones:
     * - Técnico asignado activo con permiso de avance.
     * - Cotización aprobada y presupuesto autorizado por el cliente.
     * - La orden debe encontrarse actualmente en estado 'En reparación'.
     *
     * @param  User  $user  Empleado técnico.
     * @param  OrdenServicio  $orden  Orden de servicio.
     * @return bool Verdadero si califica para pasar a fase de pruebas.
     */
    public function sendRepairToTesting(
        User $user,
        OrdenServicio $orden
    ): bool {
        $tieneAsignacionActiva = $orden
            ->asignaciones()
            ->where('empleado_id', $user->id)
            ->where('activo', true)
            ->exists();

        return $user->can('ordenes.registrar_avance')
            && $tieneAsignacionActiva
            && $orden->estado_revision_cotizacion ===
                EstadoRevisionCotizacion::APROBADA
            && $orden->autorizacion ===
                EstadoAutorizacion::AUTORIZADA->value
            && $orden->estado ===
                EstadoOrden::EN_REPARACION->value;
    }

    /**
     * Determina si un técnico puede marcar la reparación como lista para entrega al cliente.
     *
     * Precondiciones:
     * - Técnico asignado activo con permiso de avance.
     * - Cotización aprobada y presupuesto autorizado.
     * - La orden debe encontrarse en estado 'En pruebas'.
     *
     * @param  User  $user  Empleado técnico.
     * @param  OrdenServicio  $orden  Orden de servicio.
     * @return bool Verdadero si puede marcarse como lista para entrega.
     */
    public function markRepairReadyForDelivery(
        User $user,
        OrdenServicio $orden
    ): bool {
        $tieneAsignacionActiva = $orden
            ->asignaciones()
            ->where('empleado_id', $user->id)
            ->where('activo', true)
            ->exists();

        return $user->can('ordenes.registrar_avance')
            && $tieneAsignacionActiva
            && $orden->estado_revision_cotizacion ===
                EstadoRevisionCotizacion::APROBADA
            && $orden->autorizacion ===
                EstadoAutorizacion::AUTORIZADA->value
            && $orden->estado ===
                EstadoOrden::EN_PRUEBAS->value;
    }

    /**
     * Determina si el técnico puede enviar la cotización a revisión del supervisor.
     *
     * Precondiciones:
     * - Técnico con asignación activa y permiso 'ordenes.solicitar_revision_cotizacion'.
     * - Orden no finalizada.
     * - La cotización debe estar en estado 'sin_solicitar' o 'rechazada' (permitiendo correcciones).
     *
     * @param  User  $user  Empleado técnico.
     * @param  OrdenServicio  $orden  Orden de servicio.
     * @return bool Verdadero si puede enviarse a revisión.
     */
    public function requestQuoteReview(
        User $user,
        OrdenServicio $orden
    ): bool {
        if (! $this->viewAssigned($user, $orden)) {
            return false;
        }

        if (
            ! $user->can(
                'ordenes.solicitar_revision_cotizacion'
            )
        ) {
            return false;
        }

        if (
            in_array(
                $orden->estado,
                EstadoOrden::finalizados(),
                true
            )
        ) {
            return false;
        }

        return in_array(
            $orden->estado_revision_cotizacion,
            [
                EstadoRevisionCotizacion::SIN_SOLICITAR,
                EstadoRevisionCotizacion::RECHAZADA,
            ],
            true
        );
    }

    /**
     * Determina si un supervisor o administrador puede inspeccionar la cotización pendiente.
     *
     * @param  User  $user  Supervisor o administrador.
     * @param  OrdenServicio  $orden  Orden evaluada.
     * @return bool Verdadero si tiene permisos y la cotización está pendiente.
     */
    public function viewQuoteReview(
        User $user,
        OrdenServicio $orden
    ): bool {
        $puedeRevisar =
            $user->can('ordenes.aprobar_cotizacion')
            || $user->can('ordenes.rechazar_cotizacion');

        return $puedeRevisar
            && $orden->estado_revision_cotizacion ===
                EstadoRevisionCotizacion::PENDIENTE;
    }

    /**
     * Determina si un supervisor o administrador puede aprobar la cotización técnica.
     *
     * @param  User  $user  Usuario revisor.
     * @param  OrdenServicio  $orden  Orden evaluada.
     * @return bool Verdadero si posee el permiso 'ordenes.aprobar_cotizacion' y está pendiente.
     */
    public function approveQuoteReview(
        User $user,
        OrdenServicio $orden
    ): bool {
        return $user->can('ordenes.aprobar_cotizacion')
            && $orden->estado_revision_cotizacion ===
                EstadoRevisionCotizacion::PENDIENTE;
    }

    /**
     * Determina si un supervisor o administrador puede rechazar la cotización y regresarla al técnico.
     *
     * @param  User  $user  Usuario revisor.
     * @param  OrdenServicio  $orden  Orden evaluada.
     * @return bool Verdadero si posee el permiso 'ordenes.rechazar_cotizacion' y está pendiente.
     */
    public function rejectQuoteReview(
        User $user,
        OrdenServicio $orden
    ): bool {
        return $user->can('ordenes.rechazar_cotizacion')
            && $orden->estado_revision_cotizacion ===
                EstadoRevisionCotizacion::PENDIENTE;
    }

    /**
     * Determina si el cliente propietario puede autorizar o rechazar el presupuesto.
     *
     * Precondiciones:
     * - El usuario debe ser el dueño de la orden.
     * - La cotización debe haber sido formalmente aprobada por un supervisor.
     * - La orden debe encontrarse en estado 'Esperando autorización'.
     * - El estado de autorización actual debe ser 'pendiente'.
     *
     * @param  User  $user  Cliente autenticado.
     * @param  OrdenServicio  $orden  Orden de servicio.
     * @return bool Verdadero si el cliente puede responder al presupuesto.
     */
    public function authorizeBudget(
        User $user,
        OrdenServicio $orden
    ): bool {
        return $this->esPropietario($user, $orden)
            && $orden->estado_revision_cotizacion ===
                EstadoRevisionCotizacion::APROBADA
            && $orden->estado ===
                EstadoOrden::ESPERANDO_AUTORIZACION->value
            && $orden->autorizacion ===
                EstadoAutorizacion::PENDIENTE->value;
    }

    /**
     * Determina si un miembro del personal puede registrar la entrega física del equipo al cliente.
     *
     * Precondiciones:
     * - Permiso 'ordenes.actualizar'.
     * - Cotización aprobada y presupuesto autorizado.
     * - Orden en estado 'Listo para entrega'.
     * - Costo final definido.
     * - Sin fecha de entrega previa registrada (no entregar dos veces).
     *
     * @param  User  $user  Personal de taller o mostrador.
     * @param  OrdenServicio  $orden  Orden de servicio.
     * @return bool Verdadero si cumple todos los requisitos de entrega física.
     */
    public function deliver(
        User $user,
        OrdenServicio $orden
    ): bool {
        return $user->can('ordenes.actualizar')
            && $orden->estado_revision_cotizacion ===
                EstadoRevisionCotizacion::APROBADA
            && $orden->autorizacion ===
                EstadoAutorizacion::AUTORIZADA->value
            && $orden->estado ===
                EstadoOrden::LISTO_PARA_ENTREGA->value
            && $orden->costo_final !== null
            && $orden->fecha_entrega === null;
    }

    /**
     * Determina si el usuario puede descargar el comprobante en formato PDF de la orden.
     *
     * @param  User  $user  Usuario solicitante.
     * @param  OrdenServicio  $orden  Orden de servicio.
     * @return bool Verdadero si el usuario es el cliente dueño de la orden.
     */
    public function downloadPdf(
        User $user,
        OrdenServicio $orden
    ): bool {
        return $this->esPropietario($user, $orden);
    }

    /**
     * Valida si el identificador de usuario coincide con el propietario registrado en la orden.
     *
     * @param  User  $user  Usuario evaluado.
     * @param  OrdenServicio  $orden  Orden a comprobar.
     * @return bool Verdadero si es el propietario.
     */
    private function esPropietario(
        User $user,
        OrdenServicio $orden
    ): bool {
        return (int) $orden->user_id === (int) $user->id;
    }
}

