<?php

namespace App\Http\Controllers\Admin;

// ---------------------------------------------------------
// IMPORTACIONES DE CONTROLADORES Y MODELOS
// ---------------------------------------------------------
use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\OrdenServicio;
use App\Models\Servicio;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    // =========================================================
    // VISTA PRINCIPAL DEL PANEL ADMINISTRATIVO
    // =========================================================

    /**
     * Muestra el dashboard general del sistema, distinguiendo
     * entre el flujo de taller de computadoras y restaurantes.
     */
    public function index(Request $request): View
    {
        /** @var User $usuario */
        $usuario = $request->user();

        // ---------------------------------------------------------
        // 1. RESOLUCIÓN DE SUCURSAL Y EMPRESA ACTIVA
        // ---------------------------------------------------------
        $sucursalActiva = $usuario->sucursales()->with('empresa')->first();

        // Acceso directo con operador estándar -> para cumplir con PHPStan Nivel 8
        $empresa = $sucursalActiva->empresa ?? Empresa::query()->first();
        $giro = $empresa->giro_comercial ?? 'computadoras_tecnologia';

        // ---------------------------------------------------------
        // 2. SEGMENTACIÓN DE MÉTRICAS SEGÚN GIRO COMERCIAL
        // ---------------------------------------------------------
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
