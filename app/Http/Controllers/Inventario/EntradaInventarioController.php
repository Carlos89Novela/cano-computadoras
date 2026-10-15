<?php

namespace App\Http\Controllers\Inventario;

use App\Actions\Inventario\RegistrarEntradaInventario;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventario\RegistrarEntradaInventarioRequest;
use App\Models\Almacen;
use App\Models\Empresa;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EntradaInventarioController extends Controller
{
    // ---------------------------------------------------------
    // FORMULARIO DE ENTRADA
    // Carga los almacenes y productos activos pertenecientes
    // a la empresa indicada mediante la ruta.
    // ---------------------------------------------------------

    public function create(
        Empresa $empresa
    ): View {
        // -----------------------------------------------------
        // ALMACENES DISPONIBLES
        // Solo se muestran almacenes activos que permitan
        // administrar existencias.
        // -----------------------------------------------------

        $almacenes = Almacen::query()
            ->where(
                'empresa_id',
                $empresa->id
            )
            ->where(
                'activo',
                true
            )
            ->where(
                'permite_existencias',
                true
            )
            ->orderBy('nombre')
            ->get([
                'id',
                'codigo',
                'nombre',
                'es_virtual',
            ]);

        // -----------------------------------------------------
        // PRODUCTOS DISPONIBLES
        // Solo se muestran productos activos pertenecientes a
        // la empresa seleccionada.
        // -----------------------------------------------------

        $productos = Producto::query()
            ->where(
                'empresa_id',
                $empresa->id
            )
            ->where(
                'activo',
                true
            )
            ->orderBy('nombre')
            ->get([
                'id',
                'sku',
                'nombre',
                'unidad_medida',
                'costo_referencia',
            ]);

        // -----------------------------------------------------
        // VISTA
        // Entrega la empresa y los catálogos necesarios para
        // construir el formulario.
        // -----------------------------------------------------

        /** @var view-string $vista */
        $vista = 'inventario.entradas.create';

        return view(
            $vista,
            compact(
                'empresa',
                'almacenes',
                'productos'
            )
        );
    }

    // ---------------------------------------------------------
    // REGISTRAR ENTRADA
    // Ejecuta la acción de dominio después de validar el
    // almacén, producto, cantidad, costo y motivo.
    // ---------------------------------------------------------

    public function store(
        RegistrarEntradaInventarioRequest $request,
        Empresa $empresa,
        RegistrarEntradaInventario $registrarEntrada
    ): RedirectResponse {
        // -----------------------------------------------------
        // ACTOR
        // El Form Request comprobó el permiso, pero se exige
        // una instancia válida antes de ejecutar la acción.
        // -----------------------------------------------------

        $actor = $request->user();

        abort_unless(
            $actor instanceof User,
            403
        );

        // -----------------------------------------------------
        // DATOS VALIDADOS
        // Recupera exclusivamente la información aceptada por
        // el Form Request.
        // -----------------------------------------------------

        $datos = $request->validated();

        $almacenId = $datos['almacen_id']
            ?? null;

        $productoId = $datos['producto_id']
            ?? null;

        abort_unless(
            is_int($almacenId)
            || is_string($almacenId),
            422
        );

        abort_unless(
            is_int($productoId)
            || is_string($productoId),
            422
        );

        // -----------------------------------------------------
        // ALMACÉN
        // Recupera el almacén dentro del ámbito de la empresa.
        // -----------------------------------------------------

        $almacen = Almacen::query()
            ->where(
                'empresa_id',
                $empresa->id
            )
            ->findOrFail(
                $almacenId
            );

        // -----------------------------------------------------
        // PRODUCTO
        // Recupera el producto dentro del mismo ámbito
        // empresarial.
        // -----------------------------------------------------

        $producto = Producto::query()
            ->where(
                'empresa_id',
                $empresa->id
            )
            ->findOrFail(
                $productoId
            );

        // -----------------------------------------------------
        // ACCIÓN DE DOMINIO
        // Actualiza la existencia consolidada y registra el
        // movimiento de entrada correspondiente.
        // -----------------------------------------------------

        $registrarEntrada->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            actor: $actor,
            datos: [
                'cantidad' => $datos['cantidad'],
                'costo_unitario' => $datos['costo_unitario'],
                'motivo' => $datos['motivo'],
            ]
        );

        // -----------------------------------------------------
        // REDIRECCIÓN
        // Regresa al formulario para permitir otra entrada y
        // muestra la confirmación de la operación.
        // -----------------------------------------------------

        return redirect()
            ->route(
                'inventario.entradas.create',
                [
                    'empresa' => $empresa->id,
                ]
            )
            ->with(
                'success',
                'La entrada de inventario fue registrada correctamente.'
            );
    }
}
