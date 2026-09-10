<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrdenServicio;
use App\Models\Servicio;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $totalOrdenes = OrdenServicio::query()->count();

        $ordenesSinAsignar = 0;

        $totalServicios = Servicio::query()->count();

        $totalUsuarios = User::query()->count();

        return view(
            'admin.dashboard',
            compact(
                'totalOrdenes',
                'ordenesSinAsignar',
                'totalServicios',
                'totalUsuarios'
            )
        );
    }
}
