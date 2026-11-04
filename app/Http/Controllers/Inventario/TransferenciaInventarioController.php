<?php

namespace App\Http\Controllers\Inventario;

use App\Actions\Inventario\TransferirInventario;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventario\TransferenciaInventarioRequest;
use App\Models\Almacen;
use App\Models\Empresa;
use App\Models\TransferenciaInventario;
use App\Models\TransferenciaInventarioDetalle;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TransferenciaInventarioController extends Controller
{
    public function index(Empresa $empresa): View
    {
        return view('inventario.transferencias.index', compact('empresa'));
    }

    public function data(Empresa $empresa): JsonResponse
    {
        $transferencias = TransferenciaInventario::query()
            ->where('empresa_id', $empresa->id)
            ->with([
                'almacenOrigen:id,nombre,codigo',
                'almacenDestino:id,nombre,codigo',
                'creadoPor:id,name',
                'detalles.producto:id,nombre,sku',
            ])
            ->latest('transferido_at')
            ->get();

        $data = $transferencias->map(function (TransferenciaInventario $t) {
            return [
                'id' => $t->id,
                'folio' => $t->folio,
                'almacen_origen' => $t->almacenOrigen->nombre.' ('.$t->almacenOrigen->codigo.')',
                'almacen_destino' => $t->almacenDestino->nombre.' ('.$t->almacenDestino->codigo.')',
                'motivo' => $t->motivo,
                'total_lineas' => $t->total_lineas,
                'costo_total' => '$'.number_format((float) $t->costo_total, 2),
                'creado_por' => $t->creadoPor->name ?? 'Sistema',
                'transferido_at' => $t->transferido_at->format('d/m/Y H:i'),
                'detalles' => $t->detalles->map(function (TransferenciaInventarioDetalle $det) {
                    return [
                        'producto' => $det->producto->sku.' - '.$det->producto->nombre,
                        'cantidad' => number_format((float) $det->cantidad, 2),
                        'costo_unitario' => '$'.number_format((float) $det->costo_unitario, 2),
                        'subtotal' => '$'.number_format((float) $det->subtotal, 2),
                    ];
                }),
            ];
        });

        return response()->json(['data' => $data]);
    }

    public function create(Empresa $empresa): View
    {
        $almacenes = $empresa->almacenes()
            ->where('activo', true)
            ->where('permite_existencias', true)
            ->orderBy('nombre')
            ->get();

        $productos = $empresa->productos()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'sku', 'nombre']);

        return view('inventario.transferencias.create', compact('empresa', 'almacenes', 'productos'));
    }

    public function store(
        TransferenciaInventarioRequest $request,
        Empresa $empresa,
        TransferirInventario $action
    ): RedirectResponse {
        /** @var Almacen $origen */
        $origen = Almacen::query()->findOrFail($request->integer('almacen_origen_id'));

        /** @var Almacen $destino */
        $destino = Almacen::query()->findOrFail($request->integer('almacen_destino_id'));

        /** @var User $actor */
        $actor = $request->user();

        $transferencia = $action->ejecutar(
            empresa: $empresa,
            origen: $origen,
            destino: $destino,
            actor: $actor,
            datos: $request->validated()
        );

        return redirect()
            ->route('inventario.transferencias.index', $empresa)
            ->with('success', "Transferencia {$transferencia->folio} completada correctamente.");
    }
}
