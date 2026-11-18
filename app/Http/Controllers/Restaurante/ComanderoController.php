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

        // 1. Resolver Empresa y Sucursal
        $sucursal = $user->sucursales()->with('empresa')->where('sucursales.activo', true)->first()
            ?? Sucursal::query()->where('codigo', 'REST-01')->first()
            ?? Sucursal::query()->first();

        $empresa = $sucursal?->empresa
            ?? Empresa::query()->where('id', 3)->first()
            ?? Empresa::query()->where('giro_comercial', 'restaurantes_alimentos')->first()
            ?? Empresa::query()->firstOrFail();

        // 2. Cargar Categorías Válidas (Excluyendo insumos crudos)
        $categorias = CategoriaProducto::query()
            ->where('activo', true)
            ->whereNotIn('nombre', ['Insumos de Cocina', 'Insumos de Barra', 'Materia Prima', 'Insumos'])
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        // 3. Cargar el Plano de Áreas y Mesas
        $areasMesas = RestauranteArea::query()
            ->where('activo', true)
            ->with(['mesas' => fn ($q) => $q->where('activo', true)->orderBy('id')])
            ->orderBy('orden')
            ->get();

        // 4. Cargar Modificadores / Extras (Validando que la columna es_extra exista en la BD para evitar Error 500)
        $extrasDisponibles = collect();
        if (Schema::hasColumn('productos', 'es_extra')) {
            $columnas = ['id', 'sku', 'nombre', 'precio_base'];
            if (Schema::hasColumn('productos', 'precio_extra')) {
                $columnas[] = 'precio_extra'; // Solo lo toma si corriste la migración
            }

            $extrasDisponibles = Producto::query()
                ->where('activo', true)
                ->where('es_extra', true)
                ->get($columnas);
        }

        // 5. Cargar Promociones y Combos Vigentes
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
            'areasMesas' => $areasMesas,
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

        // PASO 1: Identificar las categorías que SÍ pertenecen al menú vendible
        // Esto bloquea de tajo cualquier producto que esté dentro de categorías de insumos.
        $categoriasValidasIds = CategoriaProducto::query()
            ->where('activo', true)
            ->whereNotIn('nombre', [
                'Insumos de Cocina',
                'Insumos de Barra',
                'Materia Prima',
                'Insumos',
                'Materias Primas'
            ])
            ->pluck('id');

        // PASO 2: Construir la consulta principal
        $query = Producto::query()
            ->where('activo', true)
            ->whereIn('categoria_producto_id', $categoriasValidasIds);

        // Filtro por pestaña seleccionada por el mesero
        if ($categoriaId > 0) {
            $query->where('categoria_producto_id', $categoriaId);
        }

        // PASO 3: Blindaje contra Insumos y Extras
        // Si tienes la columna 'es_extra', filtramos para que los extras (ej. tocino extra)
        // no salgan como platillos principales.
        if (Schema::hasColumn('productos', 'es_extra')) {
            $query->where('es_extra', false);
        }

        // Filtramos para que SOLO aparezcan platillos elaborados (recetas)
        // Esto evita que salgan "litros de crema" o "kilos de cebolla".
        if (Schema::hasColumn('productos', 'es_receta')) {
            $query->where('es_receta', true);
        }

        // PASO 4: Formatear la respuesta JSON para Alpine.js
        $productos = $query->with('receta')->get()->map(function (Producto $p) {
            return [
                'id' => $p->id,
                'sku' => $p->sku ?? 'SKU-'.$p->id,
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

        $validated = $request->validate([
            'sucursal_id' => ['nullable', 'integer'],
            'mesa_o_referencia' => ['required', 'string', 'max:60'],
            'notas_generales' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.producto_id' => ['required', 'integer'],
            'items.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'items.*.notas_especiales' => ['nullable', 'string', 'max:255'],
        ]);

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
    // SECCIÓN 4: CONTROL DE MESAS, CUENTAS Y ALERTAS
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

    public function alertasListas(Request $request): JsonResponse
    {
        $hacePoco = now()->subSeconds(30);

        $itemsListos = ComandaItem::query()
            ->where('estado', 'listo')
            ->where('updated_at', '>=', $hacePoco)
            ->with(['producto:id,nombre', 'comanda:id,mesa_o_referencia,folio'])
            ->latest('updated_at')
            ->take(5)
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'tipo' => 'item',
                'mesa' => $item->comanda?->mesa_o_referencia ?? 'Mesa',
                'folio' => $item->comanda?->folio,
                'platillo' => $item->producto?->nombre ?? 'Platillo',
                'estacion' => $item->estacion_cocina,
                'timestamp' => $item->updated_at->timestamp,
            ]);

        $comandasDespachadas = Comanda::query()
            ->where('estado', 'completada')
            ->where('updated_at', '>=', $hacePoco)
            ->latest('updated_at')
            ->take(5)
            ->get()
            ->map(fn ($cmd) => [
                'id' => 'cmd_' . $cmd->id,
                'tipo' => 'comanda',
                'mesa' => $cmd->mesa_o_referencia,
                'folio' => $cmd->folio,
                'platillo' => '¡Toda la orden terminada!',
                'estacion' => 'cocina',
                'timestamp' => $cmd->updated_at->timestamp,
            ]);

        return response()->json($itemsListos->merge($comandasDespachadas)->values());
    }

    public function pedirCuentaMesa(Request $request): JsonResponse
    {
        $mesa = $request->string('mesa')->trim()->toString();

        if (empty($mesa)) {
            return response()->json(['message' => 'Nombre de mesa requerido.'], 422);
        }

        Comanda::query()
            ->where('mesa_o_referencia', $mesa)
            ->whereIn('estado', ['pendiente', 'en_preparacion', 'completada'])
            ->whereNull('venta_id')
            ->update([
                'notas_generales' => DB::raw("CONCAT(COALESCE(notas_generales, ''), ' [CUENTA PEDIDA]')"),
            ]);

        if (class_exists(RestauranteMesa::class)) {
            RestauranteMesa::query()
                ->where('numero_o_nombre', $mesa)
                ->update(['estado' => 'cuenta_pedida']);
        }

        return response()->json([
            'message' => "Cuenta solicitada para {$mesa}. Notificación enviada a Caja.",
            'mesa' => $mesa,
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
}
