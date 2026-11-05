<?php

namespace App\Http\Controllers\Supervisor;

use App\Enums\EstadoAutorizacion;
use App\Enums\EstadoOrden;
use App\Enums\EstadoRevisionCotizacion;
use App\Http\Controllers\Controller;
use App\Models\OrdenServicio;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

/**
 * Controlador del Panel de Control de Supervisión Operativa.
 *
 * Este controlador consolida las métricas clave del taller de servicio y organiza
 * los flujos de trabajo que requieren atención inmediata por parte del supervisor:
 * 1. Balanceo de cargas de trabajo: Asignación de órdenes a técnicos disponibles.
 * 2. Control de cotizaciones: Aprobación o rechazo de diagnósticos y presupuestos propuestos.
 * 3. Supervisión de calidad y entrega: Monitoreo de equipos listos para ser devueltos al cliente.
 */
class DashboardController extends Controller
{
    /**
     * Muestra la vista principal del dashboard de supervisión con métricas y colas de trabajo.
     *
     * @return View Vista Blade con contadores consolidados, listas de técnicos y órdenes prioritarias.
     */
    public function index(): View
    {
        // ---------------------------------------------------------------------
        // 1. Métricas Globales de Operación
        // ---------------------------------------------------------------------

        // Conteo histórico absoluto de órdenes recibidas en el sistema
        $totalOrdenes = OrdenServicio::query()
            ->count();

        // Conteo de órdenes activas (excluyendo estados terminales como entregado o cancelado)
        $ordenesActivas = OrdenServicio::query()
            ->whereNotIn(
                'estado',
                EstadoOrden::finalizados()
            )
            ->count();

        // Estados operativos en los cuales una orden ya no requiere asignación técnica activa
        $estadosSinAsignacionRequerida = [
            ...EstadoOrden::finalizados(),
            EstadoOrden::LISTO_PARA_ENTREGA->value,
        ];

        // Conteo de órdenes que requieren intervención técnica pero carecen de técnico asignado activo
        $ordenesSinAsignar = OrdenServicio::query()
            ->whereNotIn(
                'estado',
                $estadosSinAsignacionRequerida
            )
            ->whereDoesntHave(
                'asignaciones',
                function (Builder $consulta): void {
                    $consulta->where('activo', true);
                }
            )
            ->count();

        // Inicializador de cierres pendientes para compatibilidad de interfaz
        $cierresPendientes = 0;

        // Conteo de cotizaciones técnicas que han sido enviadas a revisión por un empleado y esperan decisión
        $cotizacionesPendientes = OrdenServicio::query()
            ->where(
                'estado_revision_cotizacion',
                EstadoRevisionCotizacion::PENDIENTE->value
            )
            ->count();

        // ---------------------------------------------------------------------
        // 2. Disponibilidad y Carga Actual de Empleados Técnicos
        // ---------------------------------------------------------------------

        // Obtiene todos los usuarios con rol de empleado técnico junto con el conteo de asignaciones activas
        $empleados = User::role('empleado')
            ->withCount([
                'asignacionesComoEmpleado as carga_activa' => function (
                    Builder $consulta
                ): void {
                    $consulta->where('activo', true);
                },
            ])
            ->orderBy('name')
            ->get([
                'id',
                'name',
            ]);

        // ---------------------------------------------------------------------
        // 3. Colas de Trabajo Prioritarias
        // ---------------------------------------------------------------------

        // Órdenes sin asignar ordenadas por antigüedad de ingreso (FIFO) para evitar rezagos en recepción
        $ordenesPendientes = OrdenServicio::query()
            ->with([
                'user:id,name',
                'equipo:id,marca,modelo',
                'servicio:id,nombre',
            ])
            ->whereNotIn(
                'estado',
                $estadosSinAsignacionRequerida
            )
            ->whereDoesntHave(
                'asignaciones',
                function (Builder $consulta): void {
                    $consulta->where('activo', true);
                }
            )
            ->oldest('fecha_ingreso')
            ->limit(20)
            ->get();

        // Órdenes que actualmente tienen un técnico asignado y se encuentran en progreso operativo
        $ordenesAsignadas = OrdenServicio::query()
            ->with([
                'user:id,name',
                'equipo:id,marca,modelo',
                'servicio:id,nombre',
                'asignacionActiva.empleado:id,name',
            ])
            ->whereNotIn(
                'estado',
                EstadoOrden::finalizados()
            )
            ->whereHas(
                'asignaciones',
                function (Builder $consulta): void {
                    $consulta->where('activo', true);
                }
            )
            ->latest('id')
            ->limit(20)
            ->get();

        // Órdenes que concluyeron su proceso técnico, cuentan con autorización y costo final, listas para entrega física
        $ordenesListasEntrega = OrdenServicio::query()
            ->with([
                'user:id,name,email',
                'equipo:id,marca,modelo,tipo,numero_serie',
            ])
            ->where(
                'estado',
                EstadoOrden::LISTO_PARA_ENTREGA->value
            )
            ->where(
                'autorizacion',
                EstadoAutorizacion::AUTORIZADA->value
            )
            ->where(
                'estado_revision_cotizacion',
                EstadoRevisionCotizacion::APROBADA->value
            )
            ->whereNotNull('costo_final')
            ->whereNull('fecha_entrega')
            ->oldest('updated_at')
            ->limit(20)
            ->get();

        // Renderiza el panel unificado de supervisión
        return view(
            'supervisor.dashboard',
            compact(
                'totalOrdenes',
                'ordenesActivas',
                'ordenesSinAsignar',
                'cotizacionesPendientes',
                'cierresPendientes',
                'empleados',
                'ordenesPendientes',
                'ordenesAsignadas',
                'ordenesListasEntrega'
            )
        );
    }
}
