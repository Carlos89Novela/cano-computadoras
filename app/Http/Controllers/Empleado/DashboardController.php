<?php

namespace App\Http\Controllers\Empleado;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $usuario = $request->user();

        $reparacionesAsignadas = 0;
        $reparacionesEnProceso = 0;
        $reparacionesEnPruebas = 0;
        $cierresDevueltos = 0;

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
