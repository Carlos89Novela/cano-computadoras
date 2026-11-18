<?php

namespace App\Http\Controllers\Productos;

// ---------------------------------------------------------
// IMPORTACIONES DE CLASES, ACCIONES Y MODELOS
// ---------------------------------------------------------
use App\Actions\Productos\CrearProducto;
use App\Http\Controllers\Controller;
use App\Http\Requests\Productos\CrearProductoRequest;
use App\Models\CategoriaProducto;
use App\Models\Empresa;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductoController extends Controller
{
    // =========================================================
    // VISTA PRINCIPAL DEL CATÁLOGO DE PRODUCTOS
    // =========================================================

    /**
     * Carga la plantilla inicial con el listado de categorías
     * activas para alimentar los filtros de búsqueda en la UI.
     */
    public function index(Empresa $empresa): View
    {
        // Recupera categorías activas ordenadas alfabéticamente
        $categorias = $empresa
            ->categoriasProducto()
            ->orderByDesc('activo')
            ->orderBy('nombre')
            ->get([
                'id',
                'nombre',
                'activo',
            ]);

        return view(
            'productos.productos.index',
            compact(
                'empresa',
                'categorias'
            )
        );
    }

    // =========================================================
    // SERVICIO AJAX PARA DATATABLES
    // =========================================================

    /**
     * Procesa búsquedas, ordenamiento dinámico, filtros por
     * categoría y estado, entregando resultados paginados en JSON.
     */
    public function data(
        Request $request,
        Empresa $empresa
    ): JsonResponse {
        // -----------------------------------------------------
        // 1. CONTEXTO DE NEGOCIO Y GIRO COMERCIAL
        // Determina si la empresa opera como restaurante para ocultar
        // o segmentar productos de tipo receta/escandallo si aplica.
        // -----------------------------------------------------
        $esRestaurante = $empresa->giro_comercial === 'restaurantes_alimentos';

        // -----------------------------------------------------
        // 2. COLUMNAS PERMITIDAS PARA ORDENAMIENTO
        // Lista blanca de columnas para prevenir inyecciones SQL.
        // -----------------------------------------------------
        $columnasPermitidas = [
            'sku' => 'productos.sku',
            'nombre' => 'productos.nombre',
            'unidad_medida' => 'productos.unidad_medida',
            'precio_base' => 'productos.precio_base',
            'estado' => 'productos.activo',
        ];

        // -----------------------------------------------------
        // 3. CONSTRUCCIÓN DE LA CONSULTA BASE
        // Delimita los registros exclusivamente a la empresa inquilina.
        // -----------------------------------------------------
        $consulta = Producto::query()
            ->where(
                'productos.empresa_id',
                $empresa->id
            )
            ->when(
                $esRestaurante,
                fn (Builder $query): Builder => $query->where('productos.es_receta', false)
            )
            ->with([
                'categoria:id,nombre',
                'proveedorPrincipal:id,nombre',
            ])
            ->select('productos.*');

        // Total absoluto de registros de la empresa antes de filtrar
        $recordsTotal = Producto::query()
            ->where(
                'empresa_id',
                $empresa->id
            )
            ->when(
                $esRestaurante,
                fn (Builder $query): Builder => $query->where('es_receta', false)
            )
            ->count();

        // -----------------------------------------------------
        // 4. FILTRADO POR CATEGORÍA
        // -----------------------------------------------------
        $categoriaId = $request->integer('categoria_id');

        if (
            $categoriaId > 0
            && CategoriaProducto::query()
                ->where('empresa_id', $empresa->id)
                ->whereKey($categoriaId)
                ->exists()
        ) {
            $consulta->where(
                'productos.categoria_producto_id',
                $categoriaId
            );
        }

        // -----------------------------------------------------
        // 5. FILTRADO POR ESTADO OPERATIVO (Activo / Inactivo)
        // -----------------------------------------------------
        $estado = $request
            ->string('estado')
            ->trim()
            ->toString();

        if ($estado === 'activo') {
            $consulta->where('productos.activo', true);
        } elseif ($estado === 'inactivo') {
            $consulta->where('productos.activo', false);
        }

        // -----------------------------------------------------
        // 6. BÚSQUEDA GLOBAL
        // -----------------------------------------------------
        $search = $request->input('search.value');

        if (
            is_string($search)
            && trim($search) !== ''
        ) {
            $search = trim($search);

            $consulta->where(
                function (Builder $query) use ($search): void {
                    $query
                        ->where('productos.sku', 'like', "%{$search}%")
                        ->orWhere('productos.codigo_barras', 'like', "%{$search}%")
                        ->orWhere('productos.nombre', 'like', "%{$search}%")
                        ->orWhere('productos.marca', 'like', "%{$search}%")
                        ->orWhere('productos.modelo', 'like', "%{$search}%")
                        ->orWhereHas(
                            'categoria',
                            function (Builder $categoria) use ($search): void {
                                $categoria->where('nombre', 'like', "%{$search}%");
                            }
                        )
                        ->orWhereHas(
                            'proveedorPrincipal',
                            function (Builder $proveedor) use ($search): void {
                                $proveedor->where('nombre', 'like', "%{$search}%");
                            }
                        );
                }
            );
        }

        $recordsFiltered = (clone $consulta)->count();

        // -----------------------------------------------------
        // 7. APLICACIÓN DE ORDENAMIENTO
        // -----------------------------------------------------
        $orderColumnIndex = $request->integer('order.0.column');
        $orderColumn = $request->input("columns.{$orderColumnIndex}.data");
        $orderDirection = strtolower((string) $request->input('order.0.dir', 'asc'));

        if (! in_array($orderDirection, ['asc', 'desc'], true)) {
            $orderDirection = 'asc';
        }

        if ($orderColumn === 'categoria') {
            $consulta->orderBy(
                CategoriaProducto::query()
                    ->select('nombre')
                    ->whereColumn(
                        'categorias_producto.id',
                        'productos.categoria_producto_id'
                    )
                    ->limit(1),
                $orderDirection
            );
        } elseif ($orderColumn === 'proveedor') {
            $consulta->orderBy(
                Proveedor::query()
                    ->select('nombre')
                    ->whereColumn(
                        'proveedores.id',
                        'productos.proveedor_principal_id'
                    )
                    ->limit(1),
                $orderDirection
            );
        } elseif (
            is_string($orderColumn)
            && array_key_exists($orderColumn, $columnasPermitidas)
        ) {
            $consulta->orderBy(
                $columnasPermitidas[$orderColumn],
                $orderDirection
            );
        } else {
            $consulta
                ->orderByDesc('productos.activo')
                ->orderBy('productos.nombre');
        }

        // -----------------------------------------------------
        // 8. PAGINACIÓN Y MAPEO DE RESULTADOS
        // -----------------------------------------------------
        $start = max($request->integer('start'), 0);
        $requestedLength = $request->integer('length', 10);
        $length = min(max($requestedLength, 1), 100);

        /** @var Collection<int, Producto> $rows */
        $rows = $consulta
            ->skip($start)
            ->take($length)
            ->get();

        $data = $rows
            ->map(
                function (Producto $producto): array {
                    return [
                        'id' => $producto->id,
                        'sku' => $producto->sku,
                        'nombre' => $producto->nombre,
                        'categoria' => $producto->categoria->nombre ?? 'Sin categoría',
                        'proveedor' => $producto->proveedorPrincipal->nombre ?? 'Sin proveedor',
                        'unidad_medida' => ucfirst($producto->unidad_medida),
                        'precio_base' => (float) $producto->precio_base,
                        'es_receta' => (bool) $producto->es_receta,
                        'estado' => $producto->activo ? 'Activo' : 'Inactivo',
                    ];
                }
            )
            ->values();

        return response()->json([
            'draw' => $request->integer('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    // =========================================================
    // FORMULARIO DE ALTA DE PRODUCTOS
    // =========================================================

    /**
     * Entrega los catálogos auxiliares (categorías, proveedores
     * y unidades de medida) autorizados para la empresa.
     */
    public function create(Empresa $empresa): View
    {
        $categorias = $empresa
            ->categoriasProducto()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get([
                'id',
                'nombre',
            ]);

        $proveedores = $empresa
            ->proveedores()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get([
                'id',
                'codigo',
                'nombre',
            ]);

        $unidadesMedida = config(
            'access_control.unidades_medida_productos',
            []
        );

        /** @var view-string $vista */
        $vista = 'productos.productos.create';

        return view(
            $vista,
            compact(
                'empresa',
                'categorias',
                'proveedores',
                'unidadesMedida'
            )
        );
    }

    // =========================================================
    // PERSISTENCIA Y REGISTRO DE PRODUCTO
    // =========================================================

    /**
     * Valida la identidad del usuario responsable y delega a la
     * Action de dominio la inserción y auditoría inmutable.
     */
    public function store(
        CrearProductoRequest $request,
        Empresa $empresa,
        CrearProducto $crearProducto
    ): RedirectResponse {
        $actor = $request->user();

        abort_unless(
            $actor instanceof User,
            403
        );

        $datos = $request->validated();

        $categoriaId = $datos['categoria_producto_id'] ?? null;

        abort_unless(
            is_int($categoriaId) || is_string($categoriaId),
            422
        );

        // Validación de pertenencia: Categoría
        $categoria = CategoriaProducto::query()
            ->where('empresa_id', $empresa->id)
            ->findOrFail($categoriaId);

        // Validación de pertenencia: Proveedor (Opcional)
        $proveedorId = $datos['proveedor_principal_id'] ?? null;

        abort_unless(
            $proveedorId === null
            || is_int($proveedorId)
            || is_string($proveedorId),
            422
        );

        $proveedor = $proveedorId === null
            ? null
            : Proveedor::query()
                ->where('empresa_id', $empresa->id)
                ->findOrFail($proveedorId);

        // Ejecución de la acción de dominio
        $crearProducto->ejecutar(
            empresa: $empresa,
            categoria: $categoria,
            proveedor: $proveedor,
            actor: $actor,
            datos: $datos,
            request: $request
        );

        return redirect()
            ->route(
                'productos.index',
                [
                    'empresa' => $empresa->id,
                ]
            )
            ->with(
                'success',
                'El producto fue creado correctamente.'
            );
    }
}
