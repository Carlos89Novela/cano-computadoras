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
        $user =$request->user();

        $sucursal =$user->sucursales()->with('empresa')->where('sucursales.activo', true)->first()
            ?? Sucursal::query()->where('codigo', 'REST-01')->first()
            ?? Sucursal::query()->first();

        $empresa =$sucursal?->empresa ?? Empresa::query()->where('giro_comercial', 'restaurantes_alimentos')->firstOrFail();

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

        $sucursal = $user->sucursales()->with('empresa')->first();
        $empresaId = $sucursal?->empresa_id ?? 3;
        $sucursalId = $request->integer('sucursal_id', $sucursal?->id ?? 0);
        $estacion = $request->string('estacion')->trim()->toString();

        $query = Comanda::query()
            ->where('empresa_id', $empresaId)
            ->when($sucursalId > 0, fn ($q) => $q->where('sucursal_id', $sucursalId))
            ->whereIn('estado', ['pendiente', 'en_preparacion'])
            ->with([
                'mesero:id,name',
                'items' => function ($q) use ($estacion) {
                    $q->when($estacion !== '' && $estacion !== 'todas', function ($sq) use ($estacion) {
                        if ($estacion === 'barra_postres') {
                            $sq->whereIn('estacion_cocina', ['barra', 'postres']);
                        } else {
                            $sq->where('estacion_cocina', $estacion);
                        }
                    })->with('producto:id,nombre,sku');
                },
            ])
            ->orderBy('enviada_a_cocina_at', 'asc');

        // Solo mostrar comandas que tengan platillos pendientes/en preparación en ESTA estación
        $comandas = $query->get()->filter(function (Comanda $c) {
            return $c->items->where('estado', '!=', 'listo')->isNotEmpty();
        })->values();

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
     * Despacha únicamente los platillos de la comanda que pertenecen a la estación activa.
     * Si la comanda aún tiene platillos en otras estaciones, se mantiene abierta.
     */
    public function completarComanda(Request $request, Comanda $comanda): JsonResponse
    {
        $estacion = $request->string('estacion', 'todas')->toString();

        // 1. Marcar como listos SOLO los platillos de esta estación
        $queryItems = $comanda->items()->where('estado', '!=', 'listo');
        if ($estacion !== 'todas' && $estacion !== '') {
            if ($estacion === 'barra_postres') {
                $queryItems->whereIn('estacion_cocina', ['barra', 'postres']);
            } else {
                $queryItems->where('estacion_cocina', $estacion);
            }
        }
        $queryItems->update(['estado' => 'listo']);

        // 2. Verificar si TODA la comanda (todas las estaciones) ya terminó
        $itemsPendientesTotales = $comanda->items()->where('estado', '!=', 'listo')->count();

        if ($itemsPendientesTotales === 0) {
            $comanda->update([
                'estado' => 'completada',
                'finalizada_at' => now(),
            ]);
        } else {
            $comanda->update(['estado' => 'en_preparacion']);
        }

        return response()->json([
            'message' => 'Estación despachada con éxito.',
            'comanda_terminada_completamente' => $itemsPendientesTotales === 0,
        ]);
    }
}
