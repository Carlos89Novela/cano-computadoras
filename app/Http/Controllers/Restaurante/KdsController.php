<?php

namespace App\Http\Controllers\Restaurante;

use App\Http\Controllers\Controller;
use App\Models\Comanda;
use App\Models\ComandaItem;
use App\Models\Empresa;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View as ViewFacade;

class KdsController extends Controller
{
    /**
     * Renderiza la vista principal del Kitchen Display System en pantalla completa.
     */
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        /** @var Empresa $empresa */
        $empresa = $user->empresa ?? Empresa::query()->firstOrFail();

        /** @var Sucursal|null $sucursal */
        $sucursal = $user->sucursal
            ?? $user->sucursales()->where('sucursales.activo', true)->first()
            ?? Sucursal::query()->where('empresa_id', $empresa->id)->first();

        return ViewFacade::make('restaurante.kds.index', [
            'empresa' => $empresa,
            'sucursal' => $sucursal,
        ]);
    }

    /**
     * Retorna el listado reactivo de comandas activas para la estación solicitada.
     */
    public function comandasActivas(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $sucursalId = $request->integer('sucursal_id');
        $estacion = $request->string('estacion')->trim()->toString();

        $query = Comanda::query()
            ->where('empresa_id', $user->empresa_id ?? 1)
            ->when($sucursalId > 0, fn ($q) => $q->where('sucursal_id', $sucursalId))
            ->whereIn('estado', ['pendiente', 'en_preparacion'])
            ->with([
                'mesero:id,name',
                'items' => function ($q) use ($estacion) {
                    $q->when($estacion !== '' && $estacion !== 'todas', fn ($sq) => $sq->where('estacion_cocina', $estacion))
                        ->with('producto:id,nombre,sku');
                },
            ])
            ->orderBy('enviada_a_cocina_at', 'asc');

        $comandas = $query->get()->filter(fn (Comanda $c) => $c->items->isNotEmpty())->values();

        return response()->json($comandas);
    }

    /**
     * Avanza el estado de un item individual o de toda la comanda.
     */
    public function cambiarEstadoItem(Request $request, ComandaItem $item): JsonResponse
    {
        $nuevoEstado = $request->string('estado')->toString();

        if (! in_array($nuevoEstado, ['pendiente', 'en_preparacion', 'listo'], true)) {
            return response()->json(['message' => 'Estado inválido.'], 422);
        }

        $item->update(['estado' => $nuevoEstado]);

        // Si todos los items de la comanda están listos, se marca la comanda completa
        $comanda = $item->comanda;
        $itemsPendientes = $comanda->items()->where('estado', '!=', 'listo')->count();

        if ($itemsPendientes === 0) {
            $comanda->update([
                'estado' => 'completada',
                'finalizada_at' => now(),
            ]);
        } elseif ($comanda->estado === 'pendiente' && $nuevoEstado === 'en_preparacion') {
            $comanda->update(['estado' => 'en_preparacion']);
        }

        return response()->json([
            'message' => 'Estado actualizado.',
            'comanda_completada' => $itemsPendientes === 0,
        ]);
    }

    /**
     * Completa toda la comanda de golpe.
     */
    public function completarComanda(Comanda $comanda): JsonResponse
    {
        $comanda->items()->update(['estado' => 'listo']);
        $comanda->update([
            'estado' => 'completada',
            'finalizada_at' => now(),
        ]);

        return response()->json(['message' => 'Comanda marcada como completada.']);
    }
}
