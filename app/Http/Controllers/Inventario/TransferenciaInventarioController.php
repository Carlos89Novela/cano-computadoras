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
        /** @var User $actor */
        $actor = auth()->user();
        $sucursalesPermitidas = $actor->sucursalesOperativasIds($empresa->id);

        $transferencias = TransferenciaInventario::query()
            ->where('empresa_id', $empresa->id)
            ->where(function ($q) use ($sucursalesPermitidas) {
                $q->whereHas('almacenOrigen', function ($sub) use ($sucursalesPermitidas) {
                    $sub->whereIn('sucursal_id', $sucursalesPermitidas);
                })
                    ->orWhereHas('almacenDestino', function ($sub) use ($sucursalesPermitidas) {
                        $sub->whereIn('sucursal_id', $sucursalesPermitidas);
                    });
            })
            ->with([
                'almacenOrigen:id,nombre,codigo,sucursal_id',
                'almacenDestino:id,nombre,codigo,sucursal_id',
                'solicitadoPor:id,name',
                'despachadoPor:id,name',
                'recibidoPor:id,name',
                'detalles.producto:id,nombre,sku',
            ])
            ->latest('id')
            ->get();

        $data = $transferencias->map(function (TransferenciaInventario $t) use ($empresa, $actor) {
            $puedeDespachar = $t->estado === TransferenciaInventario::ESTADO_SOLICITADA
                && $actor->puedeGestionarAlmacen($t->almacenOrigen);

            $puedeRecibir = $t->estado === TransferenciaInventario::ESTADO_EN_TRANSITO
                && $actor->puedeGestionarAlmacen($t->almacenDestino);

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
                    'puede_despachar' => $puedeDespachar,
                    'url_despachar' => route('inventario.transferencias.despachar.view', [$empresa, $t]),
                    'puede_recibir' => $puedeRecibir,
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
        /** @var User $actor */
        $actor = auth()->user();
        $sucursalesPermitidas = $actor->sucursalesOperativasIds($empresa->id);

        $almacenesDestino = $empresa->almacenes()
            ->where('activo', true)
            ->where('permite_existencias', true)
            ->where('es_virtual', false)
            ->whereIn('sucursal_id', $sucursalesPermitidas)
            ->orderBy('nombre')
            ->get();

        $almacenesOrigen = $empresa->almacenes()
            ->where('activo', true)
            ->where('permite_existencias', true)
            ->where('es_virtual', false)
            ->orderBy('nombre')
            ->get();

        $productos = $empresa->productos()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'sku', 'nombre']);

        /** @var view-string $view */
        $view = 'inventario.transferencias.create';

        return view($view, [
            'empresa' => $empresa,
            'almacenesOrigen' => $almacenesOrigen,
            'almacenesDestino' => $almacenesDestino,
            'productos' => $productos,
        ]);
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

        abort_unless(
            $actor->puedeGestionarAlmacen($destino),
            403,
            'No tienes permiso para solicitar mercancía hacia este almacén de destino.'
        );

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

        /** @var User $actor */
        $actor = auth()->user();
        abort_unless(
            $actor->puedeGestionarAlmacen($transferencia->almacenOrigen),
            403,
            'No tienes permisos para despachar mercancía de esta sucursal.'
        );

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

        abort_unless(
            $actor->puedeGestionarAlmacen($transferencia->almacenOrigen),
            403,
            'No tienes permisos para despachar mercancía de esta sucursal.'
        );

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

        /** @var User $actor */
        $actor = auth()->user();
        abort_unless(
            $actor->puedeGestionarAlmacen($transferencia->almacenDestino),
            403,
            'No tienes permisos para recibir mercancía en esta sucursal.'
        );

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

        abort_unless(
            $actor->puedeGestionarAlmacen($transferencia->almacenDestino),
            403,
            'No tienes permisos para recibir mercancía en esta sucursal.'
        );

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
