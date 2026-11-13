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
use Illuminate\Support\Facades\View as ViewFacade;

class ComanderoController extends Controller
{
    public function __construct(
        protected GenerarComandaService $comandaService
    ) {}

    /**
     * Muestra la interfaz táctil adaptada para tablets de meseros.
     */
    public function index(Request $request): View
    {
        /** @var User $user */
        $user =$request->user();

        // 1. Obtener explícitamente la empresa restaurantera (La Fogata Bistro)
        $empresa = Empresa::query()->where('id', 3)->first()
            ?? Empresa::query()->where('giro_comercial', 'restaurantes_alimentos')->first()
            ?? Empresa::query()->firstOrFail();

        // 2. Obtener la sucursal de restaurante perteneciente a esa empresa
        $sucursal =$user->sucursales()
            ->where('sucursales.empresa_id', $empresa->id)
            ->where('sucursales.activo', true)
            ->first()
            ?? Sucursal::query()->where('empresa_id', $empresa->id)->where('codigo', 'REST-01')->first()
            ?? Sucursal::query()->where('empresa_id', $empresa->id)->first();

        // 3. Cargar solo categorías de venta al público (excluir materia prima / insumos)
        $categorias = CategoriaProducto::query()
            ->where('empresa_id', $empresa->id)
            ->where('activo', true)
            ->where('nombre', 'not like', 'Insumos%')
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        // 4. Cargar áreas y mesas físicas reales del salón
        $areasMesas = RestauranteArea::query()
            ->where('empresa_id', $empresa->id)
            ->where('activo', true)
            ->with(['mesas' => function ($q) {$q->where('activo', true)->orderBy('id');
            }])
            ->orderBy('orden')
            ->get();

        // 5. Cargar extras disponibles
        $extrasDisponibles = Producto::query()
            ->where('empresa_id', $empresa->id)
            ->where('activo', true)
            ->where('es_extra', true)
            ->get(['id', 'sku', 'nombre', 'precio_extra']);

        // 6. Cargar promociones y combos vigentes en este momento
        $promocionesVigentes = Promocion::query()
            ->where('empresa_id', $empresa->id)
            ->where('activo', true)
            ->with(['items.producto', 'items.categoria'])
            ->get()
            ->filter(fn (Promocion $p) =>$p->estaVigenteAhora())
            ->values();

        return ViewFacade::make('restaurante.comandero.index', [
            'empresa' => $empresa,
            'sucursal' => $sucursal,
            'categorias' => $categorias,
            'areasMesas' => $areasMesas,
            'extrasDisponibles' => $extrasDisponibles,
            'promocionesVigentes' => $promocionesVigentes,
        ]);
    }

    /**
     * Entrega el listado de productos activos con su estación de preparación.
     */
    public function catalogo(Request $request): JsonResponse
    {
        /** @var User $user */
        $user =$request->user();

        $sucursal =$user->sucursales()->with('empresa')->first();
        $empresa =$sucursal?->empresa
            ?? Empresa::query()->where('id', 3)->first()
            ?? Empresa::query()->where('giro_comercial', 'restaurantes_alimentos')->first()
            ?? Empresa::query()->first();

        $empresaId =$empresa?->id ?? 3;
        $categoriaId =$request->integer('categoria_id');

        $query = Producto::query()
            ->where('empresa_id', $empresaId)
            ->where('activo', true)
            ->where('es_extra', false);

        if ($categoriaId > 0) {
            $query->where('categoria_producto_id',$categoriaId);
        }

        $productos = $query->get()->map(function (Producto$p) {
            $estacion = 'cocina';$tiempoMin = 10;

            if (method_exists($p, 'receta') &&$p->receta) {
                $estacion =$p->receta->estacion_cocina ?? 'cocina';
                $tiempoMin =$p->receta->tiempo_preparacion_minutos ?? 10;
            }

            return [
                'id' => $p->id,
                'sku' => $p->sku,
                'nombre' => $p->nombre,
                'precio' => (float) $p->precio_base,
                'es_receta' => (bool) $p->es_receta,
                'categoria_producto_id' => $p->categoria_producto_id,
                'estacion' => $estacion,
                'tiempo_min' => $tiempoMin,
            ];
        });

        return response()->json($productos);
    }

    /**
     * Valida la orden tomada por el mesero y la despacha a las pantallas KDS.
     */
    public function enviarComanda(Request $request): JsonResponse
    {
        /** @var User $user */
        $user =$request->user();

        $sucursalUsuario =$user->sucursales()->with('empresa')->first()
            ?? Sucursal::query()->where('codigo', 'REST-01')->first();

        $empresaId =$sucursalUsuario?->empresa_id ?? 3;
        $sucursalId = $sucursalUsuario?->id ?? $request->integer('sucursal_id');

        $validated =$request->validate([
            'sucursal_id' => ['nullable', 'integer'],
            'mesa_o_referencia' => ['required', 'string', 'max:60'],
            'notas_generales' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.producto_id' => ['required', 'integer'],
            'items.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'items.*.notas_especiales' => ['nullable', 'string', 'max:255'],
        ]);

        $comanda =$this->comandaService->ejecutar([
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

    /**
     * Devuelve todos los platillos acumulados y no liquidados de la mesa actual.
     */
    public function consumoActivoMesa(Request $request, string$mesa): JsonResponse
    {
        /** @var User $user */
        $user =$request->user();
        $sucursal =$user->sucursales()->with('empresa')->where('sucursales.activo', true)->first();
        $empresaId =$sucursal?->empresa_id ?? 3;

        $comandas = Comanda::query()
            ->where('empresa_id', $empresaId)
            ->where('mesa_o_referencia', $mesa)
            ->where('estado', '!=', 'cancelada')
            ->whereNull('venta_id')
            ->with(['items.producto'])
            ->orderBy('id', 'asc')
            ->get();

        $itemsAcumulados = [];$totalConsumo = 0.0;

        foreach ($comandas as$cmd) {
            foreach ($cmd->items as$item) {
                if (str_contains((string) $item->notas_especiales, '[PAGADO-VTA:')) {
                    continue;
                }

                $precio = (float) ($item->producto?->precio_base ?? 0);
                $subtotal = $precio * (float)$item->cantidad;
                $totalConsumo +=$subtotal;

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
            'tiene_comanda_activa' => count($itemsAcumulados) > 0, 'items' =>$itemsAcumulados,
            'total' => round($totalConsumo, 2),
        ]);
    }

    /**
     * Consulta platillos o comandas que acaban de ser marcados como listos o despachados.
     */
    public function alertasListas(Request $request): JsonResponse
    {
        /** @var User $user */
        $user =$request->user();
        $sucursal =$user->sucursales()->with('empresa')->where('sucursales.activo', true)->first();
        $empresaId =$sucursal?->empresa_id ?? 3;

        $hacePoco = now()->subSeconds(30);

        $itemsListos = ComandaItem::query()
            ->whereHas('comanda', function ($q) use ($empresaId) {
                $q->where('empresa_id',$empresaId);
            })
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
            ->where('empresa_id', $empresaId)
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

        return response()->json(
            $itemsListos->merge($comandasDespachadas)->values()
        );
    }

    /**
     * El mesero solicita la cuenta desde el comandero.
     */
    public function pedirCuentaMesa(Request $request): JsonResponse
    {
        /** @var User $user */
        $user =$request->user();
        $sucursal =$user->sucursales()->with('empresa')->where('sucursales.activo', true)->first();
        $empresaId =$sucursal?->empresa_id ?? 3;

        $mesa =$request->string('mesa')->trim()->toString();

        if (empty($mesa)) {
            return response()->json(['message' => 'Nombre de mesa requerido.'], 422);
        }

        Comanda::query()
            ->where('empresa_id', $empresaId)
            ->where('mesa_o_referencia', $mesa)
            ->whereIn('estado', ['pendiente', 'en_preparacion', 'completada'])
            ->whereNull('venta_id')
            ->update([
                'notas_generales' => DB::raw("CONCAT(COALESCE(notas_generales, ''), ' [CUENTA PEDIDA]')"),
            ]);

        RestauranteMesa::query()
            ->where('numero_o_nombre', $mesa)
            ->update(['estado' => 'cuenta_pedida']);

        return response()->json([
            'message' => "Cuenta solicitada para {$mesa}. Notificación enviada a Caja.",
            'mesa' => $mesa,
        ]);
    }

    /**
     * Devuelve el estado en tiempo real de todas las áreas y mesas.
     */
    public function estadoMesas(): JsonResponse
    {
        $empresaId = Empresa::query()->where('id', 3)->value('id') ?? 3;

        $areas = RestauranteArea::query()
            ->where('empresa_id', $empresaId)
            ->where('activo', true)
            ->with(['mesas' => fn ($q) =>$q->where('activo', true)->orderBy('id')])
            ->orderBy('orden')
            ->get();

        return response()->json($areas);
    }
}