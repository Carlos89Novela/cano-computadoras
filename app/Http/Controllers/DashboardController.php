<?php

namespace App\Http\Controllers;

use App\Enums\EstadoOrden;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $usuario = $request->user();

        $totalEquipos = $usuario
            ->equipos()
            ->count();

        $resumenReparaciones = DB::table('orden_servicios')
            ->where('user_id', $usuario->id)
            ->selectRaw('COUNT(*) as total_reparaciones')
            ->selectRaw(
                'SUM(CASE WHEN estado NOT IN (?, ?) THEN 1 ELSE 0 END) as reparaciones_activas',
                EstadoOrden::finalizados()
            )
            ->selectRaw(
                'SUM(CASE WHEN estado = ? THEN 1 ELSE 0 END) as reparaciones_terminadas',
                [
                    EstadoOrden::ENTREGADO->value,
                ]
            )
            ->first();

        $totalReparaciones = (int) (
            $resumenReparaciones->total_reparaciones ?? 0
        );

        $reparacionesActivas = (int) (
            $resumenReparaciones->reparaciones_activas ?? 0
        );

        $reparacionesTerminadas = (int) (
            $resumenReparaciones->reparaciones_terminadas ?? 0
        );

        $ordenesRecientes = $usuario
            ->ordenesServicio()
            ->with([
                'equipo:id,marca,modelo',
                'servicio:id,nombre',
            ])
            ->latest('id')
            ->limit(5)
            ->get();

        return view(
            'dashboard',
            compact(
                'totalEquipos',
                'reparacionesActivas',
                'reparacionesTerminadas',
                'totalReparaciones',
                'ordenesRecientes'
            )
        );
    }
}
