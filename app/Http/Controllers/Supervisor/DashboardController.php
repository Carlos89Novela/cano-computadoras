<?php

namespace App\Http\Controllers\Supervisor;

use App\Enums\EstadoOrden;
use App\Http\Controllers\Controller;
use App\Models\OrdenServicio;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $totalOrdenes = OrdenServicio::query()
            ->count();

        $ordenesActivas = OrdenServicio::query()
            ->whereNotIn(
                'estado',
                EstadoOrden::finalizados()
            )
            ->count();

        $ordenesSinAsignar = OrdenServicio::query()
            ->whereNotIn(
                'estado',
                EstadoOrden::finalizados()
            )
            ->whereDoesntHave(
                'asignaciones',
                function (Builder $consulta): void {
                    $consulta->where('activo', true);
                }
            )
            ->count();

        $cierresPendientes = 0;

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

        $ordenesPendientes = OrdenServicio::query()
            ->with([
                'user:id,name',
                'equipo:id,marca,modelo',
                'servicio:id,nombre',
            ])
            ->whereNotIn(
                'estado',
                EstadoOrden::finalizados()
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

        return view(
            'supervisor.dashboard',
            compact(
                'totalOrdenes',
                'ordenesActivas',
                'ordenesSinAsignar',
                'cierresPendientes',
                'empleados',
                'ordenesPendientes',
                'ordenesAsignadas'
            )
        );
    }
}
