<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use App\Models\Almacen;
use App\Models\Empresa;
use App\Models\ExistenciaAlmacen;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class InventarioKardexController extends Controller
{
    public function index(Empresa $empresa): View
    {
        /** @var User $actor */
        $actor = auth()->user();
        $sucursalesPermitidas = $actor->sucursalesOperativasIds($empresa->id);

        $almacenes = $empresa->almacenes()
            ->where('activo', true)
            ->where('permite_existencias', true)
            ->where(function ($q) use ($sucursalesPermitidas) {
                $q->whereIn('sucursal_id', $sucursalesPermitidas)
                    ->orWhere('es_virtual', true);
            })
            ->orderBy('nombre')
            ->get();

        /** @var view-string $view */
        $view = 'inventario.existencias.index';

        return view($view, compact('empresa', 'almacenes'));
    }

    public function dataExistencias(Request $request, Empresa $empresa): JsonResponse
    {
        /** @var User $actor */
        $actor = auth()->user();
        $sucursalesPermitidas = $actor->sucursalesOperativasIds($empresa->id);

        $almacenId = $request->integer('almacen_id');

        $query = ExistenciaAlmacen::query()
            ->where('empresa_id', $empresa->id)
            ->whereHas('almacen', function ($q) use ($sucursalesPermitidas) {
                $q->where('activo', true)
                    ->where(function ($sub) use ($sucursalesPermitidas) {
                        $sub->whereIn('sucursal_id', $sucursalesPermitidas)
                            ->orWhere('es_virtual', true);
                    });
            })
            ->with([
                'producto:id,sku,nombre,unidad_medida',
                'almacen:id,nombre,codigo,sucursal_id',
                'almacen.sucursal:id,nombre',
            ]);

        if ($almacenId > 0) {
            $query->where('almacen_id', $almacenId);
        }

        $existencias = $query->get();

        $data = $existencias->map(function (ExistenciaAlmacen $e) use ($empresa): array {
            $disponible = bcsub((string) $e->cantidad, (string) $e->cantidad_reservada, 4);

            return [
                'id' => $e->id,
                'producto_id' => $e->producto_id,
                'almacen_id' => $e->almacen_id,
                'sku' => $e->producto->sku,
                'producto' => $e->producto->nombre,
                'unidad' => $e->producto->unidad_medida,
                'almacen' => $e->almacen->nombre.' ('.$e->almacen->codigo.')',
                'sucursal' => $e->almacen->sucursal->nombre ?? 'Virtual / Global',
                'cantidad' => number_format((float) $e->cantidad, 2),
                'reservada' => number_format((float) $e->cantidad_reservada, 2),
                'disponible' => number_format((float) $disponible, 2),
                'costo_promedio' => '$'.number_format((float) $e->costo_promedio, 2),
                'valor_total' => '$'.number_format((float) bcmul((string) $e->cantidad, (string) $e->costo_promedio, 2), 2),
                'url_kardex' => route('inventario.kardex.show', [
                    'empresa' => $empresa,
                    'producto' => $e->producto_id,
                    'almacen' => $e->almacen_id,
                ]),
            ];
        });

        return response()->json(['data' => $data]);
    }

    public function kardex(Empresa $empresa, Producto $producto, ?Almacen $almacen = null): View
    {
        /** @var User $actor */
        $actor = auth()->user();

        if ($almacen !== null) {
            abort_unless($actor->puedeGestionarAlmacen($almacen), 403, 'No tienes acceso al almacén solicitado.');
        }

        /** @var view-string $view */
        $view = 'inventario.existencias.kardex';

        return view($view, compact('empresa', 'producto', 'almacen'));
    }

    public function dataKardex(Empresa $empresa, Producto $producto, ?Almacen $almacen = null): JsonResponse
    {
        /** @var User $actor */
        $actor = auth()->user();
        $sucursalesPermitidas = $actor->sucursalesOperativasIds($empresa->id);

        $query = MovimientoInventario::query()
            ->where('empresa_id', $empresa->id)
            ->where('producto_id', $producto->id)
            ->with(['almacen:id,nombre,codigo,sucursal_id', 'usuario:id,name'])
            ->whereHas('almacen', function ($q) use ($sucursalesPermitidas) {
                $q->whereIn('sucursal_id', $sucursalesPermitidas)->orWhere('es_virtual', true);
            });

        if ($almacen !== null) {
            abort_unless($actor->puedeGestionarAlmacen($almacen), 403);
            $query->where('almacen_id', $almacen->id);
        }

        $movimientos = $query->latest('ocurrido_at')->latest('id')->get();

        $data = $movimientos->map(function (MovimientoInventario $m): array {
            $fechaOcurrido = Carbon::parse((string) $m->ocurrido_at)->format('d/m/Y H:i');

            return [
                'id' => $m->id,
                'folio' => $m->folio,
                'fecha' => $fechaOcurrido,
                'tipo' => $m->tipo,
                'almacen' => $m->almacen->nombre,
                'cantidad' => number_format((float) $m->cantidad, 2),
                'existencia_anterior' => number_format((float) $m->existencia_anterior, 2),
                'existencia_nueva' => number_format((float) $m->existencia_nueva, 2),
                'costo_unitario' => '$'.number_format((float) $m->costo_unitario, 2),
                'motivo' => $m->motivo,
                'usuario' => $m->usuario->name ?? 'Sistema',
            ];
        });

        return response()->json(['data' => $data]);
    }
}
