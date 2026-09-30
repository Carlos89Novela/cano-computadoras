<?php

namespace App\Http\Controllers\Productos;

use App\Actions\Productos\ActualizarCategoriaProducto;
use App\Actions\Productos\CambiarEstadoCategoriaProducto;
use App\Actions\Productos\CrearCategoriaProducto;
use App\Http\Controllers\Controller;
use App\Http\Requests\Productos\ActualizarCategoriaProductoRequest;
use App\Http\Requests\Productos\CambiarEstadoCategoriaProductoRequest;
use App\Http\Requests\Productos\CrearCategoriaProductoRequest;
use App\Models\CategoriaProducto;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Controlador para la Administración del Catálogo de Categorías de Productos.
 *
 * Gestiona el ciclo de vida de las clasificaciones de productos por empresa (multitenant):
 * 1. Listado paginado con trazabilidad de autoría (creación, edición y desactivación).
 * 2. Creación con validaciones de unicidad de nombre dentro del contexto de la empresa.
 * 3. Actualización de datos descriptivos y normalización de textos.
 * 4. Activación o desactivación lógica con auditoría obligatoria del motivo del cambio.
 */
class CategoriaProductoController extends Controller
{
    /**
     * Muestra la lista paginada de categorías registradas en la empresa.
     *
     * @param  Empresa  $empresa  Instancia de la empresa propietaria del catálogo.
     * @return View Vista Blade con la tabla de categorías e historial de autores.
     */
    public function index(
        Empresa $empresa
    ): View {
        // Recupera las categorías de la empresa ordenadas por estado activo y nombre alfabético
        $categorias = $empresa
            ->categoriasProducto()
            ->with([
                'creadoPor:id,name',
                'actualizadoPor:id,name',
                'desactivadoPor:id,name',
            ])
            ->orderByDesc('activo')
            ->orderBy('nombre')
            ->paginate(20)
            ->withQueryString();

        /** @var view-string $vista */
        $vista = 'productos.categorias.index';

        return view(
            $vista,
            compact(
                'empresa',
                'categorias',
            )
        );
    }

    /**
     * Muestra el formulario para registrar una nueva categoría de productos.
     *
     * @param  Empresa  $empresa  Empresa a la cual pertenecerá la nueva categoría.
     * @return View Vista del formulario de alta.
     */
    public function create(
        Empresa $empresa
    ): View {
        /** @var view-string $vista */
        $vista = 'productos.categorias.create';

        return view(
            $vista,
            compact('empresa')
        );
    }

    /**
     * Almacena una nueva categoría de productos en la base de datos.
     *
     * @param  CrearCategoriaProductoRequest  $request  Petición validada con nombre y descripción.
     * @param  Empresa  $empresa  Empresa en cuyo ámbito se creará la categoría.
     * @param  CrearCategoriaProducto  $crearCategoria  Acción de dominio que aplica la lógica de persistencia y auditoría.
     * @return RedirectResponse Redirección al índice de categorías con mensaje de éxito.
     */
    public function store(
        CrearCategoriaProductoRequest $request,
        Empresa $empresa,
        CrearCategoriaProducto $crearCategoria
    ): RedirectResponse {
        $actor = $request->user();

        abort_unless(
            $actor instanceof User,
            403
        );

        $datos = $request->validated();

        $nombre = $datos['nombre'] ?? null;
        $descripcion = $datos['descripcion'] ?? null;

        abort_unless(
            is_string($nombre),
            422
        );

        abort_unless(
            is_string($descripcion)
            || $descripcion === null,
            422
        );

        // Ejecuta la creación vinculando la categoría a la empresa y registrando el evento de auditoría
        $crearCategoria->ejecutar(
            empresa: $empresa,
            actor: $actor,
            nombre: $nombre,
            descripcion: $descripcion,
            request: $request
        );

        return redirect()
            ->route(
                'productos.categorias.index',
                [
                    'empresa' => $empresa->id,
                ]
            )
            ->with(
                'success',
                'La categoría de productos fue creada correctamente.'
            );
    }

    /**
     * Muestra el formulario de edición para una categoría existente.
     *
     * @param  Empresa  $empresa  Empresa propietaria.
     * @param  CategoriaProducto  $categoria  Categoría a modificar.
     * @return View Vista con el formulario precargado.
     */
    public function edit(
        Empresa $empresa,
        CategoriaProducto $categoria
    ): View {
        // Salvaguarda multitenant: garantiza que la categoría pertenezca a la empresa de la ruta
        abort_unless(
            (int) $categoria->empresa_id
            === (int) $empresa->id,
            404
        );

        // Carga relaciones de autoría para trazabilidad en pantalla
        $categoria->load([
            'creadoPor:id,name',
            'actualizadoPor:id,name',
            'desactivadoPor:id,name',
        ]);

        /** @var view-string $vista */
        $vista = 'productos.categorias.edit';

        return view(
            $vista,
            compact(
                'empresa',
                'categoria',
            )
        );
    }

    /**
     * Actualiza la información de una categoría existente.
     *
     * @param  ActualizarCategoriaProductoRequest  $request  Petición HTTP validada.
     * @param  Empresa  $empresa  Empresa propietaria.
     * @param  CategoriaProducto  $categoria  Categoría a actualizar.
     * @param  ActualizarCategoriaProducto  $actualizarCategoria  Acción de dominio que aplica la actualización.
     * @return RedirectResponse Redirección al formulario de edición con mensaje flash.
     */
    public function update(
        ActualizarCategoriaProductoRequest $request,
        Empresa $empresa,
        CategoriaProducto $categoria,
        ActualizarCategoriaProducto $actualizarCategoria
    ): RedirectResponse {
        $actor = $request->user();

        abort_unless(
            $actor instanceof User,
            403
        );

        $datos = $request->validated();

        $nombre = $datos['nombre'] ?? null;
        $descripcion = $datos['descripcion'] ?? null;

        abort_unless(
            is_string($nombre),
            422
        );

        abort_unless(
            is_string($descripcion)
            || $descripcion === null,
            422
        );

        // Aplica los cambios mediante la acción de dominio registrando el antes y después en auditoría
        $actualizarCategoria->ejecutar(
            empresa: $empresa,
            categoria: $categoria,
            actor: $actor,
            nombre: $nombre,
            descripcion: $descripcion,
            request: $request
        );

        return redirect()
            ->route(
                'productos.categorias.edit',
                [
                    'empresa' => $empresa->id,
                    'categoria' => $categoria->id,
                ]
            )
            ->with(
                'success',
                'La categoría de productos fue actualizada correctamente.'
            );
    }

    /**
     * Modifica el estado operativo (activo/inactivo) de una categoría de productos.
     *
     * @param  CambiarEstadoCategoriaProductoRequest  $request  Petición validada con el nuevo booleano y motivo.
     * @param  Empresa  $empresa  Empresa propietaria.
     * @param  CategoriaProducto  $categoria  Categoría cuyo estado será alterado.
     * @param  CambiarEstadoCategoriaProducto  $cambiarEstado  Acción de dominio que ejecuta la transición lógica.
     * @return RedirectResponse Redirección al índice con confirmación de reactivación o desactivación.
     */
    public function updateStatus(
        CambiarEstadoCategoriaProductoRequest $request,
        Empresa $empresa,
        CategoriaProducto $categoria,
        CambiarEstadoCategoriaProducto $cambiarEstado
    ): RedirectResponse {
        $actor = $request->user();

        abort_unless(
            $actor instanceof User,
            403
        );

        $datos = $request->validated();

        $activar = $datos['activar'] ?? null;
        $motivo = $datos['motivo'] ?? null;

        abort_unless(
            is_bool($activar),
            422
        );

        abort_unless(
            is_string($motivo),
            422
        );

        // Ejecuta el cambio de estado con trazabilidad del usuario y motivo obligatorio
        $cambiarEstado->ejecutar(
            empresa: $empresa,
            categoria: $categoria,
            actor: $actor,
            activar: $activar,
            motivo: $motivo,
            request: $request
        );

        return redirect()
            ->route(
                'productos.categorias.index',
                [
                    'empresa' => $empresa->id,
                ]
            )
            ->with(
                'success',
                $activar
                    ? 'La categoría fue reactivada correctamente.'
                    : 'La categoría fue desactivada correctamente.'
            );
    }
}

