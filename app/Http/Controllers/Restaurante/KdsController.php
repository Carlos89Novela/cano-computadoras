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

/**
 * Controlador principal del Kitchen Display System (KDS / Monitor de Cocina).
 */
class KdsController extends Controller
{
    // =========================================================
    // SECCIÓN 1: MÉTODOS PRIVADOS DE VALIDACIÓN Y PERMISOS
    // =========================================================

    /**
     * Determina si el usuario autenticado tiene nivel gerencial/supervisión
     * para visualizar el KDS Maestro ("todas" las estaciones).
     */
    private function puedeVerTodasLasEstaciones(User $user): bool
    {
        // Verificación directa optimizada para PHPStan y roles
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

    /**
     * Resuelve la empresa y sucursal activas del giro restaurantero.
     *
     * @return array{0: Empresa, 1: Sucursal|null}
     */
    private function resolverContextoRestaurante(User $user): array
    {
        $empresa = Empresa::query()
            ->where('giro_comercial', 'restaurantes_alimentos')
            ->orWhere('nombre', 'like', '%Bistro%')
            ->orWhere('nombre', 'like', '%Restaurante%')
            ->first()
            ?? $user->empresa
            ?? Empresa::query()->firstOrFail();

        $sucursal = $user->sucursales()
            ->with('empresa')
            ->whereHas('empresa', function ($q) {
                $q->where('giro_comercial', 'restaurantes_alimentos')
                    ->orWhere('nombre', 'like', '%Bistro%')
                    ->orWhere('nombre', 'like', '%Restaurante%');
            })
            ->where('sucursales.activo', true)
            ->first()
            ?? Sucursal::query()->where('empresa_id', $empresa->id)->where('activo', true)->first()
            ?? Sucursal::query()->where('codigo', 'REST-01')->first()
            ?? Sucursal::query()->first();

        return [$empresa, $sucursal];
    }

    // =========================================================
    // SECCIÓN 2: VISTAS DEL MONITOR KDS (MAESTRO E INDIVIDUALES)
    // =========================================================

    /**
     * Renderiza la vista principal del Kitchen Display System en pantalla completa,
     * filtrando estrictamente la sucursal y empresa del giro restaurantero.
     */
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        [$empresa, $sucursal] = $this->resolverContextoRestaurante($user);

        $puedeVerTodas = $this->puedeVerTodasLasEstaciones($user);
        $estacionUrl = mb_strtolower($request->string('estacion')->trim()->toString());

        $estacionesPermitidas = $puedeVerTodas
            ? ['todas', 'cocina', 'parrilla', 'barra', 'postres']
            : ['cocina', 'parrilla', 'barra', 'postres'];

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

    /**
     * Renderiza una vista KDS individual dedicada a una estación específica
     * para abrir cada monitor por separado (cocina, parrilla, barra, postres o todas).
     */
    public function estacion(Request $request, string $estacion): View
    {
        /** @var User $user */
        $user = $request->user();

        [$empresa, $sucursal] = $this->resolverContextoRestaurante($user);

        $estacionNormalizada = mb_strtolower(trim($estacion));
        $estacionesValidas = ['cocina', 'parrilla', 'barra', 'postres', 'todas'];

        if (! in_array($estacionNormalizada, $estacionesValidas, true)) {
            $estacionNormalizada = 'cocina';
        }

        return ViewFacade::make('restaurante.kds.estacion', [
            'empresa' => $empresa,
            'sucursal' => $sucursal,
            'estacionFija' => $estacionNormalizada,
        ]);
    }

    // =========================================================
    // SECCIÓN 3: ENDPOINTS REACTIVOS (API JSON)
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

        // Resolver empresa de alimentos por defecto si el usuario no la trae asignada directamente
        $empresaRestaurante = Empresa::query()
            ->where('giro_comercial', 'restaurantes_alimentos')
            ->first();
        $empresaIdFiltro = $user->empresa_id ?? $empresaRestaurante?->id ?? 1;

        $query = Comanda::query()
            ->where('empresa_id', $empresaIdFiltro)
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
     * Avanza el estado de un ítem individual y notifica cambios por estación.
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
            'comanda_id' => $comanda->id,
            'folio' => $comanda->folio,
            'mesa' => $comanda->mesa_o_referencia,
            'estacion' => $item->estacion_cocina,
            'item_nombre' => $item->producto?->nombre ?? 'Platillo',
            'estado_item' => $nuevoEstado,
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

        // Obtenemos los ítems que van a cambiar a listo para la respuesta
        $itemsAfectados = $itemsQuery->get();
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
            'comanda_id' => $comanda->id,
            'folio' => $comanda->folio,
            'mesa' => $comanda->mesa_o_referencia,
            'estacion' => $estacion !== '' ? $estacion : 'cocina',
            'comanda_completada' => $itemsPendientesTotales === 0,
        ]);
    }

    // Ejemplo en tu controlador del KDS / Comandero
    public function alertasListas(Request $request): JsonResponse
    {
        // Supongamos que buscas los ítems marcados como listos recientemente
        $itemsListos = ComandaItem::with('comanda', 'producto')
            ->where('estado', 'listo')
            ->where('updated_at', '>=', now()->subSeconds(10))
            ->get();

        $alertas = $itemsListos->map(function ($item) {
            return [
                'tipo' => 'despacho',
                'id' => $item->id,
                'estacion' => $item->estacion_cocina ?? 'cocina',
                'mesa' => $item->comanda->mesa_o_referencia ?? 'Mesa 1',
                'platillo' => $item->producto?->nombre ?? 'Platillo',
                'timestamp' => $item->updated_at->timestamp,
            ];
        });

        return response()->json($alertas);
    }
}
