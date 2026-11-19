<?php

namespace App\Http\Controllers\Restaurante;

use App\Http\Controllers\Controller;
use App\Models\CategoriaProducto;
use App\Models\Comanda;
use App\Models\ComandaItem;
use App\Models\Empresa;
use App\Models\Producto;
use App\Models\Promocion;
use App\Models\RestauranteArea;
use App\Models\RestauranteMesa;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\Restaurante\GenerarComandaService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View as ViewFacade;

/**
 * Controlador principal del Comandero Táctil (Meseros).
 * Gestiona el menú, el envío de órdenes a cocina, el consumo activo por mesa,
 * las alertas de despacho del KDS y las solicitudes de precuenta hacia el POS.
 */
class ComanderoController extends Controller
{
    public function __construct(
        protected GenerarComandaService $comandaService
    ) {}

    // =========================================================================
    // SECCIÓN 1: RENDERIZADO DE LA INTERFAZ PRINCIPAL
    // =========================================================================

    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        // 1. Resolver Empresa y Sucursal activa del giro de restaurantes
        $sucursal = $user->sucursales()->with('empresa')->where('sucursales.activo', true)->first()
            ?? Sucursal::query()->where('codigo', 'REST-01')->first()
            ?? Sucursal::query()->first();

        $empresa = $sucursal?->empresa
            ?? Empresa::query()->where('id', 3)->first()
            ?? Empresa::query()->where('giro_comercial', 'restaurantes_alimentos')->first()
            ?? Empresa::query()->firstOrFail();

        // 2. Cargar categorías válidas excluyendo insumos crudos de cocina/barra
        $categorias = CategoriaProducto::query()
            ->where('activo', true)
            ->whereNotIn('nombre', ['Insumos de Cocina', 'Insumos de Barra', 'Materia Prima', 'Insumos'])
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        // 3. Cargar hasta 20 mesas activas validando columnas existentes en BD
        $mesas = collect();
        if (class_exists(RestauranteMesa::class)) {
            $query = RestauranteMesa::query()->where('activo', true);

            if ($sucursal && Schema::hasColumn('restaurante_mesas', 'sucursal_id')) {
                $query->where('sucursal_id', $sucursal->id);
            }

            $columnasDisponibles = ['id'];
            foreach (['numero', 'nombre', 'identificador', 'estado', 'sucursal_id'] as $col) {
                if (Schema::hasColumn('restaurante_mesas', $col)) {
                    $columnasDisponibles[] = $col;
                }
            }

            $mesas = $query->orderBy('id')->take(20)->get($columnasDisponibles);
        }

        // 4. Cargar modificadores / extras válidos de la empresa
        $extrasDisponibles = collect();
        if (Schema::hasColumn('productos', 'es_extra')) {
            $columnas = ['id', 'sku', 'nombre', 'precio_base'];
            if (Schema::hasColumn('productos', 'precio_extra')) {
                $columnas[] = 'precio_extra';
            }

            $extrasDisponibles = Producto::query()
                ->where('activo', true)
                ->where('es_extra', true)
                ->where('empresa_id', $empresa->id)
                ->get($columnas);
        }

        // 5. Cargar promociones y combos vigentes en este momento
        $promocionesVigentes = collect();
        if (class_exists(Promocion::class)) {
            $promocionesVigentes = Promocion::query()
                ->where('activo', true)
                ->with(['items.producto', 'items.categoria'])
                ->get()
                ->filter(fn ($p) => method_exists($p, 'estaVigenteAhora') ? $p->estaVigenteAhora() : true)
                ->values();
        }

        return ViewFacade::make('restaurante.comandero.index', [
            'empresa' => $empresa,
            'sucursal' => $sucursal,
            'categorias' => $categorias,
            'mesas' => $mesas,
            'extrasDisponibles' => $extrasDisponibles,
            'promocionesVigentes' => $promocionesVigentes,
        ]);
    }

    // =========================================================================
    // SECCIÓN 2: ALIMENTACIÓN DEL CATÁLOGO VÍA AJAX
    // =========================================================================

    public function catalogo(Request $request): JsonResponse
    {
        $categoriaId = $request->integer('categoria_id');

        // Obtener IDs de categorías válidas para venta (sin insumos ni materia prima)
        $categoriasValidasIds = CategoriaProducto::query()
            ->where('activo', true)
            ->whereNotIn('nombre', [
                'Insumos de Cocina',
                'Insumos de Barra',
                'Materia Prima',
                'Insumos',
                'Materias Primas',
            ])
            ->pluck('id');

        $query = Producto::query()
            ->where('activo', true)
            ->whereIn('categoria_producto_id', $categoriasValidasIds);

        if ($categoriaId > 0) {
            $query->where('categoria_producto_id', $categoriaId);
        }

        if (Schema::hasColumn('productos', 'es_extra')) {
            $query->where('es_extra', false);
        }

        if (Schema::hasColumn('productos', 'es_receta')) {
            $query->where('es_receta', true);
        }

        // Mapear productos incluyendo estación y tiempo estimado desde su receta
        $productos = $query->with('receta')->get()->map(function (Producto $p) {
            return [
                'id' => $p->id,
                'sku' => $p->sku ?? 'SKU-' . $p->id,
                'nombre' => $p->nombre ?? 'Platillo',
                'precio' => (float) ($p->precio_base ?? $p->precio ?? 0),
                'es_receta' => isset($p->es_receta) ? (bool) $p->es_receta : true,
                'categoria_producto_id' => $p->categoria_producto_id,
                'estacion' => $p->receta?->estacion_cocina ?? 'cocina',
                'tiempo_min' => $p->receta?->tiempo_preparacion_minutos ?? 10,
            ];
        });

        return response()->json($productos);
    }

    // =========================================================================
    // SECCIÓN 3: PROCESAMIENTO Y DESPACHO A COCINA
    // =========================================================================

    public function enviarComanda(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $sucursalId = $request->integer('sucursal_id', 1);
        $sucursal = Sucursal::query()->find($sucursalId);
        $empresaId = $sucursal?->empresa_id ?? 3;

        // Validar estructura de la comanda entrante
        $validated = $request->validate([
            'sucursal_id' => ['nullable', 'integer'],
            'mesa_o_referencia' => ['required', 'string', 'max:60'],
            'notas_generales' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.producto_id' => ['required', 'integer'],
            'items.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'items.*.notas_especiales' => ['nullable', 'string', 'max:255'],
        ]);

        // Delegar creación y envío a cocina al servicio especializado
        $comanda = $this->comandaService->ejecutar([
            'empresa_id' => (int) $empresaId,
            'sucursal_id' => (int) $sucursalId,
            'mesero_user_id' => (int) $user->id,
            'mesa_o_referencia' => $validated['mesa_o_referencia'],
            'notas_generales' => $validated['notas_generales'] ?? null,
            'items' => $validated['items'],
        ]);

        return response()->json([
            'message' => 'Comanda enviada a cocina exitosamente.',
            'folio' => $comanda->folio,
            'comanda_id' => $comanda->id,
        ]);
    }

    // =========================================================================
    // SECCIÓN 4: CONSUMO ACTIVO POR MESA
    // =========================================================================

    public function consumoActivoMesa(Request $request, string $mesa): JsonResponse
    {
        $comandas = Comanda::query()
            ->where('mesa_o_referencia', $mesa)
            ->where('estado', '!=', 'cancelada')
            ->whereNull('venta_id')
            ->with(['items.producto'])
            ->orderBy('id', 'asc')
            ->get();

        $itemsAcumulados = [];
        $totalConsumo = 0.0;

        foreach ($comandas as $cmd) {
            foreach ($cmd->items as $item) {
                if (str_contains((string) $item->notas_especiales, '[PAGADO-VTA:')) {
                    continue;
                }

                $precio = (float) ($item->producto?->precio_base ?? 0);
                $subtotal = $precio * (float) $item->cantidad;
                $totalConsumo += $subtotal;

                $itemsAcumulados[] = [
                    'comanda_folio' => $cmd->folio,
                    'producto_id' => $item->producto_id,
                    'nombre' => $item->producto?->nombre ?? 'Platillo',
                    'cantidad' => (float) $item->cantidad,
                    'precio_unitario' => $precio,
                    'subtotal' => $subtotal,
                    'estacion' => $item->estacion_cocina,
                    'estado' => $item->estado,
                    'comanda_estado' => $cmd->estado,
                    'notas_especiales' => $item->notas_especiales,
                ];
            }
        }

        return response()->json([
            'mesa' => $mesa,
            'tiene_comanda_activa' => count($itemsAcumulados) > 0,
            'items' => $itemsAcumulados,
            'total' => round($totalConsumo, 2),
        ]);
    }

    public function consumoMesa(Request $request): JsonResponse
    {
        $mesa = trim($request->string('mesa')->toString());
        $sucursalId = $request->integer('sucursal_id');

        // Buscar la comanda abierta más reciente de la mesa y sucursal
        $comanda = Comanda::query()
            ->when($sucursalId > 0, fn ($q) => $q->where('sucursal_id', $sucursalId))
            ->where('mesa_o_referencia', $mesa)
            ->whereNotIn('estado', ['completada', 'cancelada', 'pagada'])
            ->with(['items.producto:id,nombre,sku'])
            ->latest('id')
            ->first();

        if (! $comanda) {
            return response()->json([
                'items' => [],
                'total' => 0,
            ]);
        }

        $items = $comanda->items->map(function (ComandaItem $item) {
            $precio = (float) ($item->precio_unitario ?? $item->precio ?? $item->producto?->precio_base ?? 0);
            $cantidad = (int) ($item->cantidad ?? 1);

            return [
                'id' => $item->id,
                'nombre' => $item->producto?->nombre ?? $item->nombre ?? 'Platillo',
                'cantidad' => $cantidad,
                'precio' => $precio,
                'subtotal' => (float) ($item->subtotal ?? ($precio * $cantidad)),
                'estacion_cocina' => $item->estacion_cocina ?? 'cocina',
                'estado' => $item->estado ?? 'pendiente',
            ];
        })->values();

        return response()->json([
            'comanda_id' => $comanda->id,
            'folio' => $comanda->folio,
            'items' => $items,
            'total' => $items->sum('subtotal'),
        ]);
    }

    // =========================================================================
    // SECCIÓN 5: ALERTAS DE DESPACHO KDS Y PRECUENTA HACIA POS
    // =========================================================================

    public function alertasListas(Request $request): JsonResponse
    {
        $hacePoco = now()->subSeconds(25);

        $itemsListos = ComandaItem::query()
            ->where('estado', 'listo')
            ->where('updated_at', '>=', $hacePoco)
            ->with(['producto:id,nombre', 'comanda:id,mesa_o_referencia,folio,notas_generales'])
            ->latest('updated_at')
            ->take(5)
            ->get()
            ->filter(fn ($item) => ! str_contains((string) ($item->notas_especiales ?? ''), '[PAGADO-VTA:'))
            ->map(fn ($item) => [
                'id' => 'item_' . $item->id . '_' . $item->updated_at->timestamp,
                'tipo' => 'item',
                'mesa' => $item->comanda?->mesa_o_referencia ?? 'Mesa',
                'folio' => $item->comanda?->folio,
                'platillo' => $item->producto?->nombre ?? 'Platillo',
                'estacion' => $item->estacion_cocina,
                'timestamp' => $item->updated_at->timestamp,
            ]);

        $comandasDespachadas = Comanda::query()
            ->where('estado', 'completada')
            ->whereNotNull('finalizada_at')
            ->where('finalizada_at', '>=', $hacePoco)
            ->where('notas_generales', 'not like', '%[CUENTA PEDIDA]%')
            ->latest('finalizada_at')
            ->take(5)
            ->get()
            ->map(fn ($cmd) => [
                'id' => 'cmd_' . $cmd->id . '_' . $cmd->finalizada_at->timestamp,
                'tipo' => 'comanda',
                'mesa' => $cmd->mesa_o_referencia,
                'folio' => $cmd->folio,
                'platillo' => '¡Toda la orden terminada!',
                'estacion' => 'cocina',
                'timestamp' => $cmd->finalizada_at->timestamp,
            ]);

        return response()->json($itemsListos->merge($comandasDespachadas)->values());
    }

    public function pedirCuentaMesa(Request $request): JsonResponse
    {
        $mesa = $request->string('mesa')->trim()->toString();

        if (empty($mesa)) {
            return response()->json(['message' => 'Nombre de mesa requerido.'], 422);
        }

        $variantesMesa = array_unique([
            $mesa,
            preg_replace('/^mesa\s+/i', '', $mesa),
            str_starts_with(strtolower($mesa), 'mesa ') ? $mesa : 'Mesa ' . $mesa,
        ]);

        $comanda = Comanda::query()
            ->whereIn('mesa_o_referencia', $variantesMesa)
            ->whereIn('estado', ['pendiente', 'en_preparacion', 'completada'])
            ->whereNull('venta_id')
            ->latest('id')
            ->first();

        $sucursalId = (int) ($comanda?->sucursal_id ?? $request->integer('sucursal_id') ?: 1);

        Comanda::query()
            ->whereIn('mesa_o_referencia', $variantesMesa)
            ->whereIn('estado', ['pendiente', 'en_preparacion', 'completada'])
            ->whereNull('venta_id')
            ->where('notas_generales', 'not like', '%[CUENTA PEDIDA]%')
            ->update([
                'notas_generales' => DB::raw("CONCAT(COALESCE(notas_generales, ''), ' [CUENTA PEDIDA]')"),
            ]);

        if (class_exists(RestauranteMesa::class) && Schema::hasColumn('restaurante_mesas', 'estado')) {
            $mesaQuery = RestauranteMesa::query();

            $mesaQuery->where(function ($q) use ($variantesMesa) {
                $tieneCondicion = false;
                foreach (['nombre', 'identificador', 'numero', 'numero_o_nombre'] as $col) {
                    if (Schema::hasColumn('restaurante_mesas', $col)) {
                        $metodo = $tieneCondicion ? 'orWhereIn' : 'whereIn';
                        $q->{$metodo}($col, $variantesMesa);
                        $tieneCondicion = true;
                    }
                }
            })->update(['estado' => 'cuenta_pedida']);
        }

        $alertaId = 'precuenta_' . md5(strtolower(trim($mesa)) . '_' . now()->format('Y-m-d H:i:s'));
        $nuevaAlerta = [
            'id' => $alertaId,
            'mesa' => $mesa,
            'sucursal_id' => $sucursalId,
            'hora' => now()->format('H:i:s'),
            'timestamp' => now()->timestamp,
        ];

        foreach (array_unique([$sucursalId, 1, 0]) as $sid) {
            $claveCache = "pos_alertas_precuenta_{$sid}";
            $alertas = cache()->get($claveCache, []);
            $alertas[] = $nuevaAlerta;
            cache()->put($claveCache, array_slice($alertas, -20), now()->addMinutes(5));
        }

        return response()->json([
            'message' => "Cuenta solicitada para {$mesa}. Notificación enviada a Caja.",
            'mesa' => $mesa,
            'sucursal_id' => $sucursalId,
        ]);
    }

    public function estadoMesas(): JsonResponse
    {
        if (class_exists(RestauranteArea::class)) {
            $areas = RestauranteArea::query()
                ->where('activo', true)
                ->with(['mesas' => fn ($q) => $q->where('activo', true)->orderBy('id')])
                ->orderBy('orden')
                ->get();

            return response()->json($areas);
        }

        return response()->json([]);
    }

    // =========================================================================
    // ALERTAS DE PRECUENTA DESDE COMANDERO DE RESTAURANTE
    // =========================================================================

    public function alertasPrecuenta(Request $request): JsonResponse
    {
        $sucursalId = $request->integer('sucursal_id', 1);
        $alertas = cache()->get("pos_alertas_precuenta_{$sucursalId}", []);

        return response()->json($alertas);
    }
}
