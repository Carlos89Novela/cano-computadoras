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

class CategoriaProductoController extends Controller
{
    public function index(
        Empresa $empresa
    ): View {
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

    public function edit(
        Empresa $empresa,
        CategoriaProducto $categoria
    ): View {
        abort_unless(
            (int) $categoria->empresa_id
            === (int) $empresa->id,
            404
        );

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
