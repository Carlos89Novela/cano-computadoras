<?php

namespace App\Http\Controllers\Empleado;

use App\Enums\EstadoOrden;
use App\Http\Controllers\Controller;
use App\Models\OrdenAsignacion;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Controlador del Panel de Control para Empleados Técnicos.
 *
 * Proporciona el resumen operativo individualizado del técnico autenticado,
 * calculando las métricas en tiempo real de su carga de trabajo activa,
 * incluyendo reparaciones en proceso, en pruebas de calidad y asignaciones pendientes.
 */
class DashboardController extends Controller
{
    /**
     * Muestra el panel principal del empleado con sus métricas operativas individuales.
     *
     * @param  Request  $request  Petición HTTP entrante con el usuario autenticado.
     * @return View Vista Blade con los contadores de trabajo asignado al técnico.
     */
    public function index(Request $request): View
    {
        // ---------------------------------------------------------------------
        // 1. Verificación del Actor
        // ---------------------------------------------------------------------
        $usuario = $request->user();

        abort_unless(
            $usuario instanceof User,
            403
        );

        // ---------------------------------------------------------------------
        // 2. Consulta Base de Asignaciones Activas del Técnico
        // ---------------------------------------------------------------------
        // Se filtran únicamente asignaciones vigentes que correspondan a órdenes no finalizadas
        $consultaAsignaciones = OrdenAsignacion::query()
            ->where('empleado_id', $usuario->id)
            ->where('activo', true)
            ->whereHas('ordenServicio', function ($consulta): void {
                $consulta->whereNotIn(
                    'estado',
                    EstadoOrden::finalizados()
                );
            });

        // ---------------------------------------------------------------------
        // 3. Conteo de Métricas por Etapa Operativa
        // ---------------------------------------------------------------------

        // Total global de reparaciones activas asignadas a este técnico
        $reparacionesAsignadas = (clone $consultaAsignaciones)
            ->count();

        // Reparaciones que se encuentran activamente en la mesa de trabajo (EN_REPARACION)
        $reparacionesEnProceso = (clone $consultaAsignaciones)
            ->whereHas('ordenServicio', function ($consulta): void {
                $consulta->where(
                    'estado',
                    EstadoOrden::EN_REPARACION->value
                );
            })
            ->count();

        // Reparaciones en fase de control de calidad o pruebas de estabilidad (EN_PRUEBAS)
        $reparacionesEnPruebas = (clone $consultaAsignaciones)
            ->whereHas('ordenServicio', function ($consulta): void {
                $consulta->where(
                    'estado',
                    EstadoOrden::EN_PRUEBAS->value
                );
            })
            ->count();

        // Contador de cierres devueltos inicializado para compatibilidad de interfaz
        $cierresDevueltos = 0;

        // Renderiza la vista del panel técnico con sus indicadores
        return view(
            'empleado.dashboard',
            compact(
                'usuario',
                'reparacionesAsignadas',
                'reparacionesEnProceso',
                'reparacionesEnPruebas',
                'cierresDevueltos'
            )
        );
    }
}

