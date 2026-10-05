<?php

namespace App\Http\Controllers\Admin\Inventario;

use App\Actions\Inventario\ReservarInventarioParaOrden;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Inventario\ReservarInventarioOrdenRequest;
use App\Models\Almacen;
use App\Models\Empresa;
use App\Models\OrdenServicio;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class InventarioOrdenController extends Controller
{
    // ---------------------------------------------------------
    // RESERVAR INVENTARIO
    // Procesa una reserva para una orden y redirige nuevamente
    // al detalle administrativo de la reparación.
    // ---------------------------------------------------------

    public function reservar(
        ReservarInventarioOrdenRequest $request,
        Empresa $empresa,
        OrdenServicio $orden,
        ReservarInventarioParaOrden $reservarInventario
    ): RedirectResponse {
        // -----------------------------------------------------
        // ACTOR AUTENTICADO
        // El Form Request ya validó el permiso, pero se exige
        // una instancia válida para ejecutar la acción.
        // -----------------------------------------------------

        $actor = $request->user();

        abort_unless(
            $actor instanceof User,
            403
        );

        // -----------------------------------------------------
        // DATOS VALIDADOS
        // Obtiene almacén, producto, cantidad y motivo después
        // de aplicar las reglas del Form Request.
        // -----------------------------------------------------

        $datos = $request->validated();

        // -----------------------------------------------------
        // ALMACÉN Y PRODUCTO
        // Recupera los registros dentro de la misma empresa
        // indicada en la ruta.
        // -----------------------------------------------------

        $almacen = Almacen::query()
            ->where('empresa_id', $empresa->id)
            ->findOrFail($datos['almacen_id']);

        $producto = Producto::query()
            ->where('empresa_id', $empresa->id)
            ->findOrFail($datos['producto_id']);

        // -----------------------------------------------------
        // ACCIÓN DE DOMINIO
        // Actualiza la existencia consolidada y la reserva
        // formal asociada con la orden.
        // -----------------------------------------------------

        $reservarInventario->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            orden: $orden,
            actor: $actor,
            datos: [
                'cantidad' => $datos['cantidad'],
                'motivo' => $datos['motivo'],
            ]
        );

        // -----------------------------------------------------
        // REDIRECCIÓN
        // Regresa al detalle administrativo con confirmación.
        // -----------------------------------------------------

        return redirect()
            ->route('admin.ordenes.edit', [
                'orden' => $orden->id,
            ])
            ->with(
                'success',
                'El inventario fue reservado correctamente.'
            );
    }
}
