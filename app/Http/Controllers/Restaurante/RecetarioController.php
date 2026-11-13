<?php

namespace App\Http\Controllers\Restaurante;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View as ViewFacade;

class RecetarioController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user =$request->user();

        $sucursal =$user->sucursales()->with('empresa')->where('sucursales.activo', true)->first();
        /** @var Empresa $empresa */
        $empresa =$sucursal?->empresa ?? Empresa::query()->where('giro_comercial', 'restaurantes_alimentos')->firstOrFail();

        return ViewFacade::make('restaurante.recetas.index', [
            'empresa' => $empresa,
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        /** @var User $user */
        $user =$request->user();

        $sucursal =$user->sucursales()->with('empresa')->where('sucursales.activo', true)->first();
        $empresaId =$sucursal?->empresa_id ?? 3;

        $estacion =$request->string('estacion')->trim()->toString();
        $search =$request->string('search')->trim()->toString();

        $query = Producto::query()
            ->where('empresa_id', $empresaId)
            ->where('es_receta', true)
            ->where('activo', true)
            ->with(['categoria', 'receta.ingredientes.insumo'])
            ->when($search !== '', function ($q) use ($search) {$q->where(function ($sub) use ($search) {
                    $sub->where('nombre', 'like', "\%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                });
            });

        if ($estacion !== '' &&$estacion !== 'todas') {
            $query->whereHas('receta', fn ($q) => $q->where('estacion_cocina',$estacion));
        }

        $platillos =$query->orderBy('nombre')->get()->map(function (Producto $p) use ($empresaId) {
            $receta =$p->receta;
            $totalIngredientes =$receta?->ingredientes->count() ?? 0;
            $costoTeorico = 0.0;

            if ($receta) {
                foreach ($receta->ingredientes as$det) {
                    $insumo =$det->insumo;
                    if ($insumo) {$costoUnit = (float) ($insumo->costo_referencia ?? $insumo->precio_base);
                        $cant = (float)$det->cantidad_requerida;
                        $merma = (float)$det->porcentaje_merma;
                        $costoTeorico += ($costoUnit * $cant) * (1 + ($merma / 100));
                    }
                }
            }

            $precioVenta = (float)$p->precio_base;
            $margenBruto = $precioVenta > 0 ? (($precioVenta - $costoTeorico) /$precioVenta) * 100 : 0;

            return [
                'id' => $p->id,
                'sku' => $p->sku,
                'nombre' => $p->nombre,
                'categoria' => $p->categoria?->nombre ?? 'General',
                'estacion' => $receta?->estacion_cocina ?? 'cocina',
                'tiempo_min' => $receta?->tiempo_preparacion_minutos ?? 10,
                'rendimiento' => $receta?->rendimiento_porciones ?? 1,
                'total_ingredientes' => $totalIngredientes,
                'costo_teorico' => round($costoTeorico, 2),
                'precio_venta' => round($precioVenta, 2), 'margen_porcentaje' => round($margenBruto, 1),
                'url_editar' => url("/operacion/empresas/{$empresaId}/productos/{$p->id}/receta"),
            ];
        });

        return response()->json($platillos);
    }
}
