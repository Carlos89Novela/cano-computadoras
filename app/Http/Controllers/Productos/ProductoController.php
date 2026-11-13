<?php

namespace App\Http\Controllers\Productos;

// ---------------------------------------------------------
// IMPORTACIONES
// Clases del framework, acciones de dominio, peticiones
// form request y modelos de datos necesarios para el catálogo.
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
    // ---------------------------------------------------------
    // VISTA PRINCIPAL DEL CATÁLOGO
    // Carga la plantilla inicial con el listado de categorías
    // activas para alimentar los filtros de búsqueda en la UI.
    // ---------------------------------------------------------

    public function index(
        Empresa $empresa
    ): View {
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

    // ---------------------------------------------------------
    // SERVICIO AJAX PARA DATATABLES
    // Procesa búsquedas, ordenamiento dinámico, filtros por
    // categoría y estado, entregando resultados paginados.
    // ---------------------------------------------------------

    public function data(
        Request $request,
        Empresa $empresa
    ): JsonResponse {
        // -----------------------------------------------------
        // COLUMNAS PERMITIDAS PARA ORDENAMIENTO
        // Lista blanca de columnas para prevenir inyecciones SQL
        // o errores por nombres de campos arbitrarios.
        // -----------------------------------------------------

        $columnasPermitidas = [
            'sku' => 'productos.sku',
            'nombre' => 'productos.nombre',
            'unidad_medida' => 'productos.unidad_medida',
            'precio_base' => 'productos.precio_base',
            'estado' => 'productos.activo',
        ];

        // -----------------------------------------------------
        // IDENTIFICACIÓN DEL GIRO COMERCIAL
        // Si la empresa es de restaurante, separamos los platillos
        // de receta para listar exclusivamente insumos de cocina/barra.
        // -----------------------------------------------------

        $esRestaurante = in_array(
            $empresa->giro_comercial,
            ['restaurantes_alimentos', 'restaurante_cafeteria'],
            true
        );

        // -----------------------------------------------------
        // CONSTRUCCIÓN DE LA CONSULTA BASE
        // Delimita los registros exclusivamente a la empresa
        // inquilina (tenant) con carga ansiosa (eager loading).
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
        // FILTRADO: CATEGORÍA
        // Aplica el filtro únicamente si la categoría pertenece
        // legítimamente a la empresa en sesión.
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
        // FILTRADO: ESTADO OPERATIVO
        // Permite conmutar entre registros activos, inactivos o todos.
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
        // BÚSQUEDA GLOBAL
        // Localiza coincidencias en SKU, código de barras, nombre,
        // marca, modelo, o nombres de categoría y proveedor.
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

        // Cantidad de registros encontrados tras aplicar criterios de búsqueda
        $recordsFiltered = (
            clone $consulta
        )->count();

        // -----------------------------------------------------
        // APLICACIÓN DE ORDENAMIENTO
        // Determina la columna seleccionada en la cabecera de la tabla
        // y ejecuta subconsultas para relaciones foráneas.
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
            // Orden por defecto: productos activos primero, luego alfabético
            $consulta
                ->orderByDesc('productos.activo')
                ->orderBy('productos.nombre');
        }

        // -----------------------------------------------------
        // CONTROL DE PAGINACIÓN POR LOTES
        // Restringe el número de filas por petición para mantener
        // bajo consumo de memoria y respuesta ágil en la red.
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

        /** @var Collection<int, Producto> $rows */
        $rows = $consulta
            ->skip($start)
            ->take($length)
            ->get();

        // -----------------------------------------------------
        // TRANSFORMACIÓN Y FORMATEO DE RESPUESTA
        // Mapea la información técnica del modelo a tipos nativos
        // compatibles con el renderizado de DataTables en JavaScript,
        // incluyendo el identificador de fila y la bandera es_receta.
        // -----------------------------------------------------

        $data = $rows
            ->map(
                function (Producto $producto): array {
                    return [
                        'id' => $producto->id,
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
                        'precio_base' => (float) $producto->precio_base,
                        'es_receta' => (bool) $producto->es_receta,
                        'estado' => $producto->activo
                            ? 'Activo'
                            : 'Inactivo',
                    ];
                }
            )
            ->values();

        // -----------------------------------------------------
        // RETORNO DEL PROTOCOLO DATATABLES
        // Estructura JSON requerida por la biblioteca del cliente.
        // -----------------------------------------------------

        return response()->json([
            'draw' => $request->integer('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    // ---------------------------------------------------------
    // FORMULARIO DE ALTA DE PRODUCTOS
    // Entrega los catálogos auxiliares (categorías, proveedores
    // y unidades de medida) autorizados para la empresa.
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
    // PERSISTENCIA Y REGISTRO DE PRODUCTO
    // Valida la identidad del usuario responsable y delega a la
    // Action de dominio la inserción y auditoría inmutable.
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
        // VALIDACIÓN DE PERTENENCIA: CATEGORÍA
        // Comprueba que la categoría pertenezca a la empresa actual.
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
        // VALIDACIÓN DE PERTENENCIA: PROVEEDOR
        // Si se especificó un proveedor opcional, valida su vínculo.
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
        // EJECUCIÓN DE LA ACCIÓN DE DOMINIO
        // Invoca CrearProducto para aplicar reglas y auditoría.
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
        // REDIRECCIÓN FINAL
        // Regresa al listado con notificación de éxito.
        // -----------------------------------------------------

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
