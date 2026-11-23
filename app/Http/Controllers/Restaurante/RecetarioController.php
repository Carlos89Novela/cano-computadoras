<?php

namespace App\Http\Controllers\Restaurante;

// ---------------------------------------------------------
// IMPORTACIONES DE CONTROLADORES, MODELOS Y RUTAS
// ---------------------------------------------------------
use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\ProductoReceta;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View as ViewFacade;

/**
 * Controlador del módulo de Recetario y Escandallos (BOM) para restaurantes.
 */
class RecetarioController extends Controller
{
    // =========================================================
    // MÉTODOS PRIVADOS DE CONTEXTO
    // =========================================================

    /**
     * Resuelve la empresa del giro restaurantero asociada al usuario
     * o, en su defecto, la primera empresa de alimentos registrada.
     */
    private function resolverEmpresaRestaurante(User $user): ?Empresa
    {
        $empresaUsuario = $user->empresa;

        if ($empresaUsuario && $empresaUsuario->giro_comercial === 'restaurantes_alimentos') {
            return $empresaUsuario;
        }

        $sucursalRestaurante = $user->sucursales()
            ->with('empresa')
            ->whereHas('empresa', fn ($q) => $q->where('giro_comercial', 'restaurantes_alimentos'))
            ->first();

        return $sucursalRestaurante?->empresa
            ?? Empresa::query()->where('giro_comercial', 'restaurantes_alimentos')->first()
            ?? $empresaUsuario
            ?? Empresa::query()->first();
    }

    // =========================================================
    // VISTA PRINCIPAL DEL RECETARIO Y FICHAS TÉCNICAS (BOM)
    // =========================================================

    /**
     * Muestra la interfaz principal de recetas y costeo de platillos.
     */
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $empresa = $this->resolverEmpresaRestaurante($user);

        return ViewFacade::make('restaurante.recetas.index', [
            'empresa' => $empresa,
        ]);
    }

    // =========================================================
    // DATOS REACTIVOS PARA LA CUADRÍCULA DE RECETAS / API JSON
    // =========================================================

    /**
     * Retorna el listado filtrado de recetas y escandallos (BOM)
     * con métricas de costeo y enlace de edición por platillo.
     */
    public function data(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $empresa = $this->resolverEmpresaRestaurante($user);
        $empresaId = $empresa?->id ?? (int) ($user->empresa_id ?? 0);

        $estacion = mb_strtolower(trim($request->string('estacion')->toString()));
        $search = mb_strtolower(trim($request->string('search')->toString()));

        $withRelaciones = [
            'producto:id,nombre,sku,precio_base',
            'ingredientes.insumo:id,nombre,precio_base',
        ];

        if (method_exists(\App\Models\Producto::class, 'categoria')) {
            $withRelaciones[] = 'producto.categoria:id,nombre';
        }

        $recetas = ProductoReceta::query()
            ->when($empresaId > 0, fn ($q) => $q->where('empresa_id', $empresaId))
            ->when($estacion !== '' && $estacion !== 'todas', function ($q) use ($estacion) {
                $q->whereRaw('LOWER(estacion_cocina) = ?', [$estacion]);
            })
            ->with($withRelaciones)
            ->get()
            ->filter(function (ProductoReceta $receta) use ($search): bool {
                if ($search === '') {
                    return true;
                }

                $nombre = mb_strtolower((string) ($receta->producto?->nombre ?? ''));
                $sku = mb_strtolower((string) ($receta->producto?->sku ?? ''));

                return str_contains($nombre, $search) || str_contains($sku, $search);
            })
            ->map(function (ProductoReceta $receta): array {
                $producto = $receta->producto;
                $ingredientes = $receta->ingredientes ?? collect();

                // Calcular costo teórico sumando cantidad * precio/costo unitario disponible
                $costoTeorico = (float) $ingredientes->sum(function ($ing): float {
                    $cantidad = (float) ($ing->cantidad ?? $ing->pivot?->cantidad ?? 0);
                    $costoUnitario = (float) (
                        $ing->costo_unitario
                        ?? $ing->insumo?->precio_base
                        ?? 0
                    );

                    return $cantidad * $costoUnitario;
                });

                $precioVenta = (float) ($producto?->precio_base ?? 0);
                $margenPorcentaje = $precioVenta > 0
                    ? round((($precioVenta - $costoTeorico) / $precioVenta) * 100, 1)
                    : 0.0;

                $urlEditar = ($receta->empresa_id && $receta->producto_id)
                    ? route('productos.recetas.edit', [
                        'empresa' => $receta->empresa_id,
                        'producto' => $receta->producto_id,
                    ])
                    : '#';

                $nombreCategoria = ($producto && method_exists($producto, 'categoria') && $producto->relationLoaded('categoria'))
                    ? ($producto->categoria?->nombre ?? 'Platillo')
                    : 'Platillo';

                return [
                    'id' => $receta->id,
                    'nombre' => $producto?->nombre ?? 'Sin producto',
                    'sku' => $producto?->sku ?? '---',
                    'categoria' => $nombreCategoria,
                    'estacion' => mb_strtolower((string) ($receta->estacion_cocina ?: 'cocina')),
                    'tiempo_min' => (int) ($receta->tiempo_preparacion_minutos ?? 0),
                    'rendimiento_porciones' => (int) ($receta->rendimiento_porciones ?? 1),
                    'total_ingredientes' => $ingredientes->count(),
                    'costo_teorico' => round($costoTeorico, 2),
                    'precio_venta' => round($precioVenta, 2),
                    'margen_porcentaje' => $margenPorcentaje,
                    'url_editar' => $urlEditar,
                ];
            })
            ->values();

        return response()->json($recetas);
    }
}
