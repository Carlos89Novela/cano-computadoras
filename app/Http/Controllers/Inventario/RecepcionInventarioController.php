<?php

namespace App\Http\Controllers\Inventario;

use App\Actions\Inventario\RegistrarRecepcionInventario;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventario\RecepcionInventarioRequest;
use App\Models\Almacen;
use App\Models\Empresa;
use App\Models\OrdenCompra;
use App\Models\OrdenCompraDetalle;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\RecepcionInventario;
use App\Models\RecepcionInventarioDetalle;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RecepcionInventarioController extends Controller
{
    /**
     * Muestra el historial de recepciones con tabla DataTables.
     */
    public function index(Empresa $empresa): View
    {
        return view('inventario.recepciones.index', compact('empresa'));
    }

    /**
     * Retorna los datos de recepciones formateados para DataTables en formato JSON.
     */
    public function data(Empresa $empresa): JsonResponse
    {
        $recepciones = RecepcionInventario::query()
            ->where('empresa_id', $empresa->id)
            ->with([
                'almacen:id,nombre,codigo',
                'proveedor:id,nombre',
                'recibidoPor:id,name',
                'detalles.producto:id,nombre,sku',
            ])
            ->latest('recibido_at')
            ->get();

        $data = $recepciones->map(function (RecepcionInventario $rec) {
            $proveedorNombre = $rec->proveedor !== null ? $rec->proveedor->nombre : 'N/A';
            $recibidoPorNombre = $rec->recibidoPor !== null ? $rec->recibidoPor->name : 'Sistema';

            return [
                'id' => $rec->id,
                'folio' => $rec->folio,
                'tipo' => ucfirst(str_replace('_', ' ', $rec->tipo)),
                'almacen' => $rec->almacen->nombre.' ('.$rec->almacen->codigo.')',
                'proveedor' => $proveedorNombre,
                'referencia_externa' => $rec->referencia_externa ?? '—',
                'motivo' => $rec->motivo,
                'total_lineas' => $rec->total_lineas,
                'importe_total' => '$'.number_format((float) $rec->importe_total, 2),
                'recibido_por' => $recibidoPorNombre,
                'recibido_at' => $rec->recibido_at->format('d/m/Y H:i'),
                'detalles' => $rec->detalles->map(function (RecepcionInventarioDetalle $det) {
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

    /**
     * Muestra la pantalla para capturar una recepción múltiple de inventario.
     */
    public function create(Empresa $empresa): View
    {
        $almacenes = $empresa->almacenes()
            ->where('activo', true)
            ->where('permite_existencias', true)
            ->orderBy('nombre')
            ->get();

        $proveedores = $empresa->proveedores()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        $productos = $empresa->productos()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'sku', 'nombre', 'costo_referencia']);

        $ordenesCompra = OrdenCompra::query()
            ->where('empresa_id', $empresa->id)
            ->whereIn('estado', ['autorizada', 'recibida_parcial'])
            ->with(['proveedor', 'almacen:id,nombre'])
            ->latest('id')
            ->get();

        $tipos = RecepcionInventario::tiposPermitidos();

        return view('inventario.recepciones.create', compact(
            'empresa',
            'almacenes',
            'proveedores',
            'productos',
            'ordenesCompra',
            'tipos'
        ));
    }

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
            ->route('inventario.recepciones.index', $empresa)
            ->with('success', "Recepción {$recepcion->folio} registrada correctamente.");
    }

    public function ordenCompraData(Empresa $empresa, OrdenCompra $orden): JsonResponse
    {
        abort_unless((int) $orden->empresa_id === (int) $empresa->id, 404);

        $orden->load(['detalles.producto', 'proveedor', 'almacen']);

        /** @var Collection<int, OrdenCompraDetalle> $detallesCollection */
        $detallesCollection = $orden->detalles;

        $detalles = $detallesCollection->map(function (OrdenCompraDetalle $det) {
            $cantidad = (float) $det->cantidad;
            $recibida = (float) $det->cantidad_recibida;
            $pendiente = max(0.0, $cantidad - $recibida);
            $producto = $det->producto;

            return [
                'producto_id' => (int) $det->producto_id,
                'sku' => $producto instanceof Producto ? $producto->sku : 'N/A',
                'nombre' => $producto instanceof Producto ? $producto->nombre : 'Producto',
                'cantidad_solicitada' => $cantidad,
                'cantidad_pendiente' => $pendiente,
                'cantidad' => $pendiente,
                'costo_unitario' => (float) $det->costo_unitario,
            ];
        })->filter(fn (array $linea) => $linea['cantidad_pendiente'] > 0)->values();

        return response()->json([
            'orden_id' => $orden->id,
            'folio' => $orden->folio,
            'proveedor_id' => $orden->proveedor_id,
            'almacen_id' => $orden->almacen_id,
            'lineas' => $detalles,
        ]);
    }
}
