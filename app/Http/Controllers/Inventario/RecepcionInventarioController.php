<?php

namespace App\Http\Controllers\Inventario;

use App\Actions\Inventario\RegistrarRecepcionInventario;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventario\RecepcionInventarioRequest;
use App\Models\Almacen;
use App\Models\Empresa;
use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class RecepcionInventarioController extends Controller
{
    /**
     * Procesa y registra una recepción múltiple de inventario para una empresa.
     */
    public function store(
        RecepcionInventarioRequest $request,
        Empresa $empresa,
        RegistrarRecepcionInventario $action
    ): RedirectResponse {
        /** @var Almacen $almacen */
        $almacen = Almacen::query()->findOrFail(
            $request->integer('almacen_id')
        );

        /** @var Proveedor|null $proveedor */
        $proveedor = $request->filled('proveedor_id')
            ? Proveedor::query()->findOrFail($request->integer('proveedor_id'))
            : null;

        /** @var User $actor */
        $actor = $request->user();

        $recepcion = $action->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            proveedor: $proveedor,
            actor: $actor,
            datos: $request->validated()
        );

        return redirect()
            ->route('inventario.entradas.create', $empresa)
            ->with('success', "Recepción {$recepcion->folio} registrada correctamente.");
    }
}
