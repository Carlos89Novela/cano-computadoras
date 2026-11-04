<?php

namespace App\Http\Controllers\Inventario;

use App\Actions\Inventario\DespacharTransferenciaInventario;
use App\Actions\Inventario\RecibirTransferenciaInventario;
use App\Actions\Inventario\SolicitarTransferenciaInventario;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventario\DespachoTransferenciaRequest;
use App\Http\Requests\Inventario\RecepcionTransferenciaRequest;
use App\Http\Requests\Inventario\SolicitudTransferenciaRequest;
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
                'solicitadoPor:id,name',
                'despachadoPor:id,name',
                'recibidoPor:id,name',
                'detalles.producto:id,nombre,sku',
            ])
            ->latest('id')
            ->get();

        $data = $transferencias->map(function (TransferenciaInventario $t) use ($empresa) {
            return [
                'id' => $t->id,
                'folio' => $t->folio,
                'estado' => $t->estado,
                'almacen_origen' => $t->almacenOrigen->nombre.' ('.$t->almacenOrigen->codigo.')',
                'almacen_destino' => $t->almacenDestino->nombre.' ('.$t->almacenDestino->codigo.')',
                'motivo' => $t->motivo,
                'total_lineas' => $t->total_lineas,
                'costo_total' => '$'.number_format((float) $t->costo_total, 2),
                'solicitado_por' => $t->solicitadoPor->name ?? 'Sistema',
                'despachado_por' => $t->despachadoPor->name ?? '---',
                'recibido_por' => $t->recibidoPor->name ?? '---',
                'fecha_creacion' => $t->created_at?->format('d/m/Y H:i') ?? '---',
                'acciones' => [
                    'puede_despachar' => $t->estado === TransferenciaInventario::ESTADO_SOLICITADA,
                    'url_despachar' => route('inventario.transferencias.despachar.view', [$empresa, $t]),
                    'puede_recibir' => $t->estado === TransferenciaInventario::ESTADO_EN_TRANSITO,
                    'url_recibir' => route('inventario.transferencias.recibir.view', [$empresa, $t]),
                ],
                'detalles' => $t->detalles->map(function (TransferenciaInventarioDetalle $det) {
                    return [
                        'id' => $det->id,
                        'producto' => $det->producto->sku.' - '.$det->producto->nombre,
                        'cantidad_solicitada' => number_format((float) $det->cantidad_solicitada, 2),
                        'cantidad_despachada' => number_format((float) $det->cantidad_despachada, 2),
                        'cantidad_recibida' => number_format((float) $det->cantidad_recibida, 2),
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
            ->where('es_virtual', false)
            ->orderBy('nombre')
            ->get();

        $productos = $empresa->productos()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'sku', 'nombre']);

        return view('inventario.transferencias.create', compact('empresa', 'almacenes', 'productos'));
    }

    public function store(
        SolicitudTransferenciaRequest $request,
        Empresa $empresa,
        SolicitarTransferenciaInventario $action
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
            ->with('success', "Requisición {$transferencia->folio} solicitada correctamente. En espera de despacho.");
    }

    public function despacharView(Empresa $empresa, TransferenciaInventario $transferencia): View
    {
        abort_unless($transferencia->estado === TransferenciaInventario::ESTADO_SOLICITADA, 404);

        $transferencia->load(['almacenOrigen', 'almacenDestino', 'detalles.producto']);

        /** @var view-string $view */
        $view = 'inventario.transferencias.despachar';

        return view($view, compact('empresa', 'transferencia'));
    }

    public function despacharStore(
        DespachoTransferenciaRequest $request,
        Empresa $empresa,
        TransferenciaInventario $transferencia,
        DespacharTransferenciaInventario $action
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $request->user();

        $action->ejecutar(
            transferencia: $transferencia,
            actor: $actor,
            datos: $request->validated()
        );

        return redirect()
            ->route('inventario.transferencias.index', $empresa)
            ->with('success', "Transferencia {$transferencia->folio} despachada hacia el almacén de tránsito.");
    }

    public function recibirView(Empresa $empresa, TransferenciaInventario $transferencia): View
    {
        abort_unless($transferencia->estado === TransferenciaInventario::ESTADO_EN_TRANSITO, 404);

        $transferencia->load(['almacenOrigen', 'almacenDestino', 'detalles.producto']);

        /** @var view-string $view */
        $view = 'inventario.transferencias.recibir';

        return view($view, compact('empresa', 'transferencia'));
    }

    public function recibirStore(
        RecepcionTransferenciaRequest $request,
        Empresa $empresa,
        TransferenciaInventario $transferencia,
        RecibirTransferenciaInventario $action
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $request->user();

        $action->ejecutar(
            transferencia: $transferencia,
            actor: $actor,
            datos: $request->validated()
        );

        return redirect()
            ->route('inventario.transferencias.index', $empresa)
            ->with('success', "Mercancía recibida e ingresada al inventario de {$transferencia->almacenDestino->nombre}.");
    }
}
