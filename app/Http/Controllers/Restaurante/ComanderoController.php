<?php

namespace App\Http\Controllers\Restaurante;

// ---------------------------------------------------------
// IMPORTACIONES
// Controladores base, modelos para empresa, sucursal, productos
// y el servicio de dominio para orquestar la comanda.
// ---------------------------------------------------------

use App\Http\Controllers\Controller;
use App\Models\CategoriaProducto;
use App\Models\Empresa;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\Restaurante\GenerarComandaService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View as ViewFacade;

class ComanderoController extends Controller
{
    // ---------------------------------------------------------
    // CONSTRUCTOR
    // Inyección de dependencias para la emisión de comandas.
    // ---------------------------------------------------------

    public function __construct(
        protected GenerarComandaService $comandaService
    ) {}

    // ---------------------------------------------------------
    // VISTA PRINCIPAL: COMANDERO MÓVIL
    // Carga la interfaz táctil adaptada para tablets de meseros,
    // resolviendo la empresa, sucursal asignada y categorías.
    // ---------------------------------------------------------

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

        // Categorías activas para filtrar el catálogo en la tablet
        $categorias = CategoriaProducto::query()
            ->where('empresa_id', $empresa->id)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        return ViewFacade::make('restaurante.comandero.index', [
            'empresa' => $empresa,
            'sucursal' => $sucursal,
            'categorias' => $categorias,
        ]);
    }

    // ---------------------------------------------------------
    // CATÁLOGO DE PLATILLOS Y BEBIDAS (JSON)
    // Entrega el listado de productos activos con su estación de
    // preparación y tiempo estimado en minutos para la UI.
    // ---------------------------------------------------------

    public function catalogo(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $categoriaId = $request->integer('categoria_id');

        $productos = Producto::query()
            ->where('empresa_id', $user->empresa_id ?? 1)
            ->where('activo', true)
            ->when($categoriaId > 0, fn ($q) => $q->where('categoria_producto_id', $categoriaId))
            ->with('receta:producto_id,estacion_cocina,tiempo_preparacion_minutos')
            ->select(['id', 'sku', 'nombre', 'precio_base', 'categoria_producto_id', 'es_receta'])
            ->get()
            ->map(function (Producto $p): array {
                $receta = $p->receta;

                return [
                    'id' => $p->id,
                    'sku' => $p->sku,
                    'nombre' => $p->nombre,
                    'precio' => (float) $p->precio_base,
                    'es_receta' => $p->es_receta,
                    'estacion' => $receta !== null ? $receta->estacion_cocina : 'cocina',
                    'tiempo_min' => $receta !== null ? $receta->tiempo_preparacion_minutos : 10,
                ];
            });

        return response()->json($productos);
    }

    // ---------------------------------------------------------
    // ENVÍO DE COMANDA A PANTALLAS KDS
    // Valida la orden tomada por el mesero y la despacha al
    // servicio para segmentar platillos por estación de cocina.
    // ---------------------------------------------------------

    public function enviarComanda(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var array{
         *     sucursal_id: int,
         *     mesa_o_referencia: string,
         *     notas_generales?: string|null,
         *     items: array<int, array{
         *         producto_id: int,
         *         cantidad: numeric,
         *         notas_especiales?: string|null
         *     }>
         * } $validated */
        $validated = $request->validate([
            'sucursal_id' => ['required', 'integer'],
            'mesa_o_referencia' => ['required', 'string', 'max:60'],
            'notas_generales' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.producto_id' => ['required', 'integer'],
            'items.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'items.*.notas_especiales' => ['nullable', 'string', 'max:255'],
        ]);

        $comanda = $this->comandaService->ejecutar([
            'empresa_id' => (int) ($user->empresa_id ?? 1),
            'sucursal_id' => $validated['sucursal_id'],
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
}
