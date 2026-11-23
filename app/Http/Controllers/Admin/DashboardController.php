<?php

namespace App\Http\Controllers\Admin;

// ---------------------------------------------------------
// IMPORTACIONES DE CONTROLADORES Y MODELOS
// ---------------------------------------------------------
use App\Http\Controllers\Controller;
use App\Models\Comanda;
use App\Models\Empresa;
use App\Models\OrdenServicio;
use App\Models\Producto;
use App\Models\RestauranteMesa;
use App\Models\Servicio;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DashboardController extends Controller
{
    // =========================================================
    // VISTA PRINCIPAL DEL PANEL ADMINISTRATIVO / GERENCIAL
    // =========================================================

    /**
     * Muestra el dashboard administrativo/gerencial distinguiendo
     * giro restaurantero (con accesos a recetas, inventarios, compras, caja y KDS)
     * y giro de taller/tecnología.
     */
    public function index(Request $request): View
    {
        /** @var User $usuario */
        $usuario = $request->user();

        // 1. Resolver sucursal y empresa activa
        $sucursalActiva = $usuario->sucursales()->with('empresa')->first();
        $empresa = $sucursalActiva->empresa
            ?? Empresa::query()->where('giro_comercial', 'restaurantes_alimentos')->first()
            ?? Empresa::query()->first();

        $giro = $empresa->giro_comercial ?? 'computadoras_tecnologia';
        $empresaId = $empresa?->id ?? 1;
        $sucursalId = $sucursalActiva?->id;

        // Identificar si el usuario tiene perfil gerencial o administrativo
        $esGerente = (method_exists($usuario, 'hasAnyRole') && $usuario->hasAnyRole(['administrador', 'gerente', 'propietario']))
            || in_array(mb_strtolower((string) ($usuario->rol ?? $usuario->role ?? '')), ['administrador', 'admin', 'gerente', 'propietario'], true)
            || (bool) ($usuario->es_propietario ?? false);

        $mesasOcupadas = 0;

        // 2. Segmentación de métricas según giro comercial
        if ($giro === 'restaurantes_alimentos') {
            $totalOrdenes = Comanda::query()
                ->where('empresa_id', $empresaId)
                ->when($sucursalId, fn ($q) => $q->where('sucursal_id', $sucursalId))
                ->count();

            $ordenesSinAsignar = Comanda::query()
                ->where('empresa_id', $empresaId)
                ->when($sucursalId, fn ($q) => $q->where('sucursal_id', $sucursalId))
                ->whereIn('estado', ['pendiente', 'en_preparacion'])
                ->count();

            $totalServicios = Producto::query()
                ->where('empresa_id', $empresaId)
                ->where('activo', true)
                ->count();

            $mesasOcupadas = class_exists(RestauranteMesa::class)
                ? RestauranteMesa::query()
                    ->where('activo', true)
                    ->where(function ($q) {
                        $q->whereNotNull('comanda_actual_id')
                            ->orWhere('estado', '!=', 'disponible');
                    })
                    ->when($sucursalId && Schema::hasColumn('restaurante_mesas', 'sucursal_id'), function ($q) use ($sucursalId) {
                        $q->where(function ($sub) use ($sucursalId) {
                            $sub->where('sucursal_id', $sucursalId)->orWhereNull('sucursal_id');
                        });
                    })
                    ->count()
                : 0;

            $sucursalesIds = $empresa ? $empresa->sucursales()->pluck('id')->all() : [];
            $totalUsuarios = ! empty($sucursalesIds)
                ? User::query()->whereHas('sucursales', fn ($q) => $q->whereIn('sucursales.id', $sucursalesIds))->count()
                : 1;
        } else {
            $totalOrdenes = OrdenServicio::query()->count();
            $ordenesSinAsignar = 0;
            $totalServicios = Servicio::query()->count();
            $totalUsuarios = User::query()->count();
        }

        return view('admin.dashboard', compact(
            'empresa',
            'giro',
            'esGerente',
            'mesasOcupadas',
            'totalOrdenes',
            'ordenesSinAsignar',
            'totalServicios',
            'totalUsuarios'
        ));
    }
}
