<?php

namespace App\Http\Controllers\Supervisor;

use App\Enums\EstadoOrden;
use App\Http\Controllers\Controller;
use App\Models\OrdenServicio;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $totalOrdenes = OrdenServicio::query()->count();

        $ordenesActivas = OrdenServicio::query()
            ->whereNotIn(
                'estado',
                EstadoOrden::finalizados()
            )
            ->count();

        $ordenesSinAsignar = 0;

        $cierresPendientes = 0;

        return view(
            'supervisor.dashboard',
            compact(
                'totalOrdenes',
                'ordenesActivas',
                'ordenesSinAsignar',
                'cierresPendientes'
            )
        );
    }
}
