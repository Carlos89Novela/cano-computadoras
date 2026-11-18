<?php

namespace App\Http\Controllers\Restaurante;

// ---------------------------------------------------------
// IMPORTACIONES DE CONTROLADORES, MODELOS Y FACADES
// ---------------------------------------------------------
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
    // =========================================================
    // MÉTODOS PRIVADOS DE VALIDACIÓN Y PERMISOS
    // =========================================================

    /**
     * Determina si el usuario autenticado tiene nivel gerencial/supervisión
     * para visualizar el KDS Maestro ("todas" las estaciones).
     */
    private function puedeVerTodasLasEstaciones(User $user): bool
    {
        // Verificación directa optimizada para PHPStan
        if ($user->hasAnyRole(['administrador', 'gerente', 'subgerente', 'supervisor', 'propietario'])) {
            return true;
        }

        $rol = mb_strtolower((string) ($user->rol ?? $user->role ?? ''));

        return in_array($rol, ['administrador', 'admin', 'gerente', 'subgerente', 'supervisor', 'propietario'], true)
            || (bool) ($user->es_propietario ?? false);
    }

    /**
     * Devuelve el arreglo de valores de `estacion_cocina` correspondientes a la pantalla KDS.
     *
     * @return array<int, string>|null null significa "todas" las estaciones
     */
    private function resolverFiltroEstaciones(string $estacion, bool $puedeVerTodas): ?array
    {
        $estacion = mb_strtolower(trim($estacion));

        if ($estacion === '' || $estacion === 'todas') {
            return $puedeVerTodas ? null : ['cocina', 'postres'];
        }

        return match ($estacion) {
            'cocina' => ['cocina', 'postres'],
            'postres' => ['postres'],
            'parrilla' => ['parrilla'],
            'barra' => ['barra'],
            default => [$estacion],
        };
    }

    // =========================================================
    // VISTA PRINCIPAL DEL MONITOR KDS
    // =========================================================

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

        $puedeVerTodas = $this->puedeVerTodasLasEstaciones($user);
        $estacionUrl = mb_strtolower($request->string('estacion')->trim()->toString());

        $estacionesPermitidas = $puedeVerTodas
            ? ['todas', 'cocina', 'parrilla', 'barra']
            : ['cocina', 'parrilla', 'barra'];

        $estacionInicial = in_array($estacionUrl, $estacionesPermitidas, true)
            ? $estacionUrl
            : ($puedeVerTodas ? 'todas' : 'cocina');

        return ViewFacade::make('restaurante.kds.index', [
            'empresa' => $empresa,
            'sucursal' => $sucursal,
            'puedeVerTodas' => $puedeVerTodas,
            'estacionesPermitidas' => $estacionesPermitidas,
            'estacionInicial' => $estacionInicial,
        ]);
    }

    // =========================================================
    // ENDPOINTS REACTIVOS (API JSON)
    // =========================================================

    /**
     * Retorna el listado reactivo de comandas activas para la estación solicitada.
     */
    public function comandasActivas(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $sucursalId = $request->integer('sucursal_id');
        $estacion = $request->string('estacion')->trim()->toString();

        $puedeVerTodas = $this->puedeVerTodasLasEstaciones($user);
        $estacionesFiltro = $this->resolverFiltroEstaciones($estacion, $puedeVerTodas);

        $query = Comanda::query()
            ->where('empresa_id', $user->empresa_id ?? 1)
            ->when($sucursalId > 0, fn ($q) => $q->where('sucursal_id', $sucursalId))
            ->whereIn('estado', ['pendiente', 'en_preparacion'])
            ->with([
                'mesero:id,name',
                'items' => function ($q) use ($estacionesFiltro) {
                    $q->when($estacionesFiltro !== null, fn ($sq) => $sq->whereIn('estacion_cocina', $estacionesFiltro))
                        ->with('producto:id,nombre,sku');
                },
            ])
            ->orderBy('enviada_a_cocina_at', 'asc');

        $comandas = $query->get()->filter(function (Comanda $c) use ($estacionesFiltro) {
            if ($c->items->isEmpty()) {
                return false;
            }
            if ($estacionesFiltro !== null) {
                return $c->items->contains(fn (ComandaItem $item) => $item->estado !== 'listo');
            }

            return true;
        })->values();

        return response()->json($comandas);
    }

    /**
     * Avanza el estado de un ítem individual o de toda la comanda.
     */
    public function cambiarEstadoItem(Request $request, ComandaItem $item): JsonResponse
    {
        $nuevoEstado = $request->string('estado')->toString();

        if (! in_array($nuevoEstado, ['pendiente', 'en_preparacion', 'listo'], true)) {
            return response()->json(['message' => 'Estado inválido.'], 422);
        }

        $item->update(['estado' => $nuevoEstado]);

        $comanda = $item->comanda;
        $itemsPendientes = $comanda->items()->where('estado', '!=', 'listo')->count();

        if ($itemsPendientes === 0) {
            $comanda->update([
                'estado' => 'completada',
                'finalizada_at' => now(),
            ]);
        } elseif ($comanda->estado === 'pendiente' && in_array($nuevoEstado, ['en_preparacion', 'listo'], true)) {
            $comanda->update(['estado' => 'en_preparacion']);
        }

        return response()->json([
            'message' => 'Estado actualizado.',
            'comanda_completada' => $itemsPendientes === 0,
        ]);
    }

    /**
     * Completa los ítems de la estación actual o toda la comanda.
     */
    public function completarComanda(Request $request, Comanda $comanda): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $estacion = $request->string('estacion')->trim()->toString();
        $puedeVerTodas = $this->puedeVerTodasLasEstaciones($user);
        $estacionesFiltro = $this->resolverFiltroEstaciones($estacion, $puedeVerTodas);

        $itemsQuery = $comanda->items();
        if ($estacionesFiltro !== null) {
            $itemsQuery->whereIn('estacion_cocina', $estacionesFiltro);
        }
        $itemsQuery->update(['estado' => 'listo']);

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
            'message' => 'Orden despachada en estación.',
            'comanda_completada' => $itemsPendientesTotales === 0,
        ]);
    }
}
