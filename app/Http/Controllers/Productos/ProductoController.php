<?php

namespace App\Http\Controllers\Productos;

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
    // ---------------------------------------------------------
    // LISTADO DE PRODUCTOS
    // Muestra el catálogo perteneciente a la empresa indicada.
    // ---------------------------------------------------------

    // ---------------------------------------------------------
    // LISTADO DE PRODUCTOS
    // Muestra la pantalla del catálogo. Los registros serán
    // cargados posteriormente mediante DataTables y AJAX.
    // ---------------------------------------------------------

    public function index(
        Empresa $empresa
    ): View {
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

    // ---------------------------------------------------------
    // DATOS PARA DATATABLES
    // Devuelve los productos de la empresa con búsqueda,
    // filtros, ordenamiento y paginación del lado del servidor.
    // ---------------------------------------------------------

    public function data(
        Request $request,
        Empresa $empresa
    ): JsonResponse {
        // -----------------------------------------------------
        // COLUMNAS ORDENABLES
        // Limita el ordenamiento a columnas conocidas para evitar
        // valores arbitrarios enviados desde el navegador.
        // -----------------------------------------------------

        $columnasPermitidas = [
            'sku' => 'productos.sku',
            'nombre' => 'productos.nombre',
            'unidad_medida' => 'productos.unidad_medida',
            'precio_base' => 'productos.precio_base',
            'estado' => 'productos.activo',
        ];

        // -----------------------------------------------------
        // CONSULTA BASE
        // Restringe todos los resultados a la empresa incluida
        // en la ruta y carga las relaciones necesarias.
        // -----------------------------------------------------

        $consulta = Producto::query()
            ->where(
                'productos.empresa_id',
                $empresa->id
            )
            ->with([
                'categoria:id,nombre',
                'proveedorPrincipal:id,nombre',
            ])
            ->select('productos.*');

        $recordsTotal = Producto::query()
            ->where(
                'empresa_id',
                $empresa->id
            )
            ->count();

        // -----------------------------------------------------
        // FILTRO POR CATEGORÍA
        // Solo aplica categorías pertenecientes a la misma
        // empresa.
        // -----------------------------------------------------

        $categoriaId = $request->integer(
            'categoria_id'
        );

        if (
            $categoriaId > 0
            && CategoriaProducto::query()
                ->where(
                    'empresa_id',
                    $empresa->id
                )
                ->whereKey($categoriaId)
                ->exists()
        ) {
            $consulta->where(
                'productos.categoria_producto_id',
                $categoriaId
            );
        }

        // -----------------------------------------------------
        // FILTRO POR ESTADO
        // Permite mostrar todos, activos o inactivos.
        // -----------------------------------------------------

        $estado = $request
            ->string('estado')
            ->trim()
            ->toString();

        if ($estado === 'activo') {
            $consulta->where(
                'productos.activo',
                true
            );
        } elseif ($estado === 'inactivo') {
            $consulta->where(
                'productos.activo',
                false
            );
        }

        // -----------------------------------------------------
        // BÚSQUEDA GENERAL
        // Busca simultáneamente por SKU, código de barras,
        // nombre, marca, modelo, categoría o proveedor.
        // -----------------------------------------------------

        $search = $request->input(
            'search.value'
        );

        if (
            is_string($search)
            && trim($search) !== ''
        ) {
            $search = trim($search);

            $consulta->where(
                function (Builder $query) use (
                    $search
                ): void {
                    $query
                        ->where(
                            'productos.sku',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'productos.codigo_barras',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'productos.nombre',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'productos.marca',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'productos.modelo',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhereHas(
                            'categoria',
                            function (Builder $categoria) use (
                                $search
                            ): void {
                                $categoria->where(
                                    'nombre',
                                    'like',
                                    "%{$search}%"
                                );
                            }
                        )
                        ->orWhereHas(
                            'proveedorPrincipal',
                            function (Builder $proveedor) use (
                                $search
                            ): void {
                                $proveedor->where(
                                    'nombre',
                                    'like',
                                    "%{$search}%"
                                );
                            }
                        );
                }
            );
        }

        $recordsFiltered = (
            clone $consulta
        )->count();

        // -----------------------------------------------------
        // ORDENAMIENTO
        // Recupera la columna solicitada por DataTables y valida
        // que la dirección sea ascendente o descendente.
        // -----------------------------------------------------

        $orderColumnIndex = $request->integer(
            'order.0.column'
        );

        $orderColumn = $request->input(
            "columns.{$orderColumnIndex}.data"
        );

        $orderDirection = strtolower(
            (string) $request->input(
                'order.0.dir',
                'asc'
            )
        );

        if (
            ! in_array(
                $orderDirection,
                [
                    'asc',
                    'desc',
                ],
                true
            )
        ) {
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
            && array_key_exists(
                $orderColumn,
                $columnasPermitidas
            )
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
        // PAGINACIÓN
        // Limita a cien registros por solicitud para conservar
        // un consumo predecible de recursos.
        // -----------------------------------------------------

        $start = max(
            $request->integer('start'),
            0
        );

        $requestedLength = $request->integer(
            'length',
            10
        );

        $length = min(
            max(
                $requestedLength,
                1
            ),
            100
        );

        $rows = $consulta
            ->skip($start)
            ->take($length)
            ->get();

        /** @var Collection<int, Producto> $rows */

        // -----------------------------------------------------
        // TRANSFORMACIÓN DE FILAS
        // Entrega valores simples para que DataTables represente
        // cada producto sin exponer campos internos.
        // -----------------------------------------------------

        $data = $rows
            ->map(
                function (Producto $producto): array {
                    return [
                        'sku' => $producto->sku,
                        'nombre' => $producto->nombre,
                        'categoria' => $producto->categoria->nombre
                            ?? 'Sin categoría',
                        'proveedor' => $producto
                            ->proveedorPrincipal
                            ->nombre
                            ?? 'Sin proveedor',
                        'unidad_medida' => ucfirst(
                            $producto->unidad_medida
                        ),
                        'precio_base' => $producto
                            ->precio_base === null
                                ? null
                                : (float) $producto
                                    ->precio_base,
                        'estado' => $producto->activo
                            ? 'Activo'
                            : 'Inactivo',
                    ];
                }
            )
            ->values();

        // -----------------------------------------------------
        // RESPUESTA DATATABLES
        // Respeta el protocolo requerido por el procesamiento
        // del lado del servidor.
        // -----------------------------------------------------

        return response()->json([
            'draw' => $request->integer('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    // ---------------------------------------------------------
    // FORMULARIO DE CREACIÓN
    // Carga las categorías y proveedores activos que pertenecen
    // a la empresa seleccionada.
    // ---------------------------------------------------------

    public function create(
        Empresa $empresa
    ): View {
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

    // ---------------------------------------------------------
    // REGISTRAR PRODUCTO
    // Ejecuta la acción de dominio con los datos validados por
    // el Form Request.
    // ---------------------------------------------------------

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

        $categoriaId = $datos[
            'categoria_producto_id'
        ] ?? null;

        abort_unless(
            is_int($categoriaId)
            || is_string($categoriaId),
            422
        );

        // -----------------------------------------------------
        // CATEGORÍA
        // Recupera únicamente una categoría perteneciente a la
        // empresa indicada en la ruta.
        // -----------------------------------------------------

        $categoria = CategoriaProducto::query()
            ->where(
                'empresa_id',
                $empresa->id
            )
            ->findOrFail(
                $categoriaId
            );

        // -----------------------------------------------------
        // PROVEEDOR PRINCIPAL
        // El proveedor es opcional. Si se selecciona, debe
        // pertenecer a la misma empresa.
        // -----------------------------------------------------

        $proveedorId = $datos[
            'proveedor_principal_id'
        ] ?? null;

        abort_unless(
            $proveedorId === null
            || is_int($proveedorId)
            || is_string($proveedorId),
            422
        );

        $proveedor = $proveedorId === null
            ? null
            : Proveedor::query()
                ->where(
                    'empresa_id',
                    $empresa->id
                )
                ->findOrFail(
                    $proveedorId
                );

        // -----------------------------------------------------
        // CREACIÓN DEL PRODUCTO
        // La acción aplica las reglas de negocio, normaliza los
        // datos y registra la auditoría correspondiente.
        // -----------------------------------------------------

        $crearProducto->ejecutar(
            empresa: $empresa,
            categoria: $categoria,
            proveedor: $proveedor,
            actor: $actor,
            datos: $datos,
            request: $request
        );

        // -----------------------------------------------------
        // REDIRECCIÓN
        // Regresa al catálogo de productos de la empresa con
        // un mensaje de confirmación.
        // -----------------------------------------------------

        return redirect()
            ->route(
                'productos.productos.index',
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
