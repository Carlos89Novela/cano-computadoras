<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\OrdenServicio;
use App\Models\Servicio;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $usuario */
        $usuario = $request->user();

        // 1. Obtener la sucursal activa y empresa del usuario autenticado
        $sucursalActiva = method_exists($usuario, 'sucursales')
            ? $usuario->sucursales()->with('empresa')->first()
            : null;

        $empresa = $sucursalActiva?->empresa ?? Empresa::query()->first();
        $giro = $empresa?->giro_comercial ?? 'computadoras_tecnologia';

        // 2. Si la empresa es de giro gastronómico/restaurante, no mezclar con órdenes del taller
        if ($giro === 'restaurantes_alimentos') {
            $totalOrdenes = 0;
            $ordenesSinAsignar = 0;
            $totalServicios = 0; // Se llenará con platillos / bebidas cuando se creen

            // Contar únicamente los usuarios asignados a las sucursales de este restaurante
            $sucursalesIds = $empresa ? $empresa->sucursales()->pluck('id')->all() : [];
            $totalUsuarios = ! empty($sucursalesIds)
                ? User::query()->whereHas('sucursales', fn ($q) => $q->whereIn('sucursales.id', $sucursalesIds))->count()
                : 1;
        } else {
            // Giro computadoras / taller tradicional
            $totalOrdenes = OrdenServicio::query()->count();
            $ordenesSinAsignar = 0;
            $totalServicios = Servicio::query()->count();
            $totalUsuarios = User::query()->count();
        }

        return view('admin.dashboard', compact(
            'empresa',
            'giro',
            'totalOrdenes',
            'ordenesSinAsignar',
            'totalServicios',
            'totalUsuarios'
        ));
    }
}