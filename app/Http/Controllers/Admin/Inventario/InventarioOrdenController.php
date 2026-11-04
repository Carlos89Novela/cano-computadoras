<?php

namespace App\Http\Controllers\Admin\Inventario;

use App\Actions\Inventario\ConsumirInventarioReservadoParaOrden;
use App\Actions\Inventario\LiberarReservaInventarioParaOrden;
use App\Actions\Inventario\ReservarInventarioParaOrden;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Inventario\ConsumirInventarioOrdenRequest;
use App\Http\Requests\Admin\Inventario\LiberarReservaOrdenRequest;
use App\Http\Requests\Admin\Inventario\ReservarInventarioOrdenRequest;
use App\Models\Almacen;
use App\Models\Empresa;
use App\Models\OrdenServicio;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class InventarioOrdenController extends Controller
{
    /**
     * Reserva piezas o refacciones para una orden de servicio.
     */
    public function reservar(
        ReservarInventarioOrdenRequest $request,
        Empresa $empresa,
        OrdenServicio $orden,
        ReservarInventarioParaOrden $action
    ): RedirectResponse {
        /** @var Almacen $almacen */
        $almacen = Almacen::query()->findOrFail(
            $request->integer('almacen_id')
        );

        /** @var Producto $producto */
        $producto = Producto::query()->findOrFail(
            $request->integer('producto_id')
        );

        /** @var User $actor */
        $actor = $request->user();

        $reserva = $action->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            orden: $orden,
            actor: $actor,
            datos: $request->validated()
        );

        return back()->with(
            'success',
            "Se reservaron {$reserva->cantidad_reservada} unidad(es) de {$producto->nombre} para la orden {$orden->folio}."
        );
    }

    /**
     * Consume refacciones previamente reservadas para una orden de servicio.
     */
    public function consumir(
        ConsumirInventarioOrdenRequest $request,
        Empresa $empresa,
        OrdenServicio $orden,
        ConsumirInventarioReservadoParaOrden $action
    ): RedirectResponse {
        /** @var Almacen $almacen */
        $almacen = Almacen::query()->findOrFail(
            $request->integer('almacen_id')
        );

        /** @var Producto $producto */
        $producto = Producto::query()->findOrFail(
            $request->integer('producto_id')
        );

        /** @var User $actor */
        $actor = $request->user();

        $reserva = $action->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            orden: $orden,
            actor: $actor,
            datos: $request->validated()
        );

        return back()->with(
            'success',
            "Se consumieron {$request->input('cantidad')} unidad(es) de {$producto->nombre}. Estado de reserva: {$reserva->estado}."
        );
    }

    /**
     * Libera refacciones previamente reservadas para devolverlas al stock disponible.
     */
    public function liberar(
        LiberarReservaOrdenRequest $request,
        Empresa $empresa,
        OrdenServicio $orden,
        LiberarReservaInventarioParaOrden $action
    ): RedirectResponse {
        /** @var Almacen $almacen */
        $almacen = Almacen::query()->findOrFail(
            $request->integer('almacen_id')
        );

        /** @var Producto $producto */
        $producto = Producto::query()->findOrFail(
            $request->integer('producto_id')
        );

        /** @var User $actor */
        $actor = $request->user();

        $reserva = $action->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            orden: $orden,
            actor: $actor,
            datos: $request->validated()
        );

        return back()->with(
            'success',
            "Se liberaron {$request->input('cantidad')} unidad(es) de {$producto->nombre}. Estado de reserva: {$reserva->estado}."
        );
    }
}
