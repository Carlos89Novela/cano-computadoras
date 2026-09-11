<?php

namespace App\Http\Controllers\Empleado;

use App\Enums\EstadoOrden;
use App\Http\Controllers\Controller;
use App\Models\OrdenAsignacion;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $usuario = $request->user();

        abort_unless(
            $usuario instanceof User,
            403
        );

        $consultaAsignaciones = OrdenAsignacion::query()
            ->where('empleado_id', $usuario->id)
            ->where('activo', true)
            ->whereHas('ordenServicio', function ($consulta): void {
                $consulta->whereNotIn(
                    'estado',
                    EstadoOrden::finalizados()
                );
            });

        $reparacionesAsignadas = (clone $consultaAsignaciones)
            ->count();

        $reparacionesEnProceso = (clone $consultaAsignaciones)
            ->whereHas('ordenServicio', function ($consulta): void {
                $consulta->where(
                    'estado',
                    EstadoOrden::EN_REPARACION->value
                );
            })
            ->count();

        $reparacionesEnPruebas = (clone $consultaAsignaciones)
            ->whereHas('ordenServicio', function ($consulta): void {
                $consulta->where(
                    'estado',
                    EstadoOrden::EN_PRUEBAS->value
                );
            })
            ->count();

        $cierresDevueltos = 0;

        $asignaciones = (clone $consultaAsignaciones)
            ->with([
                'ordenServicio.user:id,name',
                'ordenServicio.equipo:id,marca,modelo',
                'ordenServicio.servicio:id,nombre',
            ])
            ->latest('asignado_at')
            ->get();

        return view(
            'empleado.dashboard',
            compact(
                'usuario',
                'reparacionesAsignadas',
                'reparacionesEnProceso',
                'reparacionesEnPruebas',
                'cierresDevueltos',
                'asignaciones'
            )
        );
    }
}
