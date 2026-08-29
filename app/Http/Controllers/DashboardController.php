<?php

namespace App\Http\Controllers;

use App\Models\OrdenServicio;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $usuario = $request->user();

        $totalEquipos = $usuario
            ->equipos()
            ->count();

        $reparacionesActivas = $usuario
            ->ordenesServicio()
            ->whereNotIn('estado', [
                'Entregado',
                'Cancelado',
            ])
            ->count();

        $reparacionesTerminadas = $usuario
            ->ordenesServicio()
            ->where('estado', 'Entregado')
            ->count();

        $totalReparaciones = $usuario
            ->ordenesServicio()
            ->count();

        $ordenesRecientes = OrdenServicio::query()
            ->with([
                'equipo',
                'servicio',
            ])
            ->where('user_id', $usuario->id)
            ->latest()
            ->limit(5)
            ->get();

        return view('dashboard', compact(
            'totalEquipos',
            'reparacionesActivas',
            'reparacionesTerminadas',
            'totalReparaciones',
            'ordenesRecientes'
        ));
    }
}