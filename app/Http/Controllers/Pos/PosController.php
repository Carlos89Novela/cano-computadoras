<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Almacen;
use App\Models\CajaTurno;
use App\Models\Empresa;
use App\Models\ExistenciaAlmacen;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Venta;
use App\Services\Pos\ProcesarDevolucionPosService;
use App\Services\Pos\RegistrarVentaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class PosController extends Controller
{
    public function __construct(
        protected RegistrarVentaService $ventaService
    ) {}

    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $empresaId = $this->resolveEmpresaId($user);

        // 1. Resolver sucursal asignada o fallback a la primera activa de la empresa
        /** @var Sucursal|null $sucursal */
        $sucursal = $user->sucursales()->first();

        if (! $sucursal) {
            /** @var Sucursal|null $sucursal */
            $sucursal = Sucursal::query()->where('empresa_id', $empresaId)->first();
        }

        $sucursalId = $sucursal !== null ? (int) $sucursal->id : null;

        // 2. Resolver almacén físico de la sucursal que permita existencias
        /** @var Almacen|null $almacen */
        $almacen = null;

        if ($sucursalId !== null) {
            $almacen = Almacen::query()
                ->where('empresa_id', $empresaId)
                ->where('sucursal_id', $sucursalId)
                ->where('activo', true)
                ->where('permite_existencias', true)
                ->where('tipo', Almacen::TIPO_PRINCIPAL)
                ->first()
                ?? Almacen::query()
                    ->where('empresa_id', $empresaId)
                    ->where('sucursal_id', $sucursalId)
                    ->where('activo', true)
                    ->where('permite_existencias', true)
                    ->first();
        }

        if (! $almacen) {
            $almacen = Almacen::query()
                ->where('empresa_id', $empresaId)
                ->where('activo', true)
                ->where('permite_existencias', true)
                ->first();
        }

        // 3. Resolver turno de caja activo para el usuario
        /** @var CajaTurno|null $turnoActivo */
        $turnoActivo = null;
        if ($sucursalId !== null) {
            $turnoActivo = CajaTurno::query()
                ->where('empresa_id', $empresaId)
                ->where('sucursal_id', $sucursalId)
                ->where('user_id', $user->id)
                ->where('estado', 'abierto')
                ->latest('id')
                ->first();
        }

        /** @var view-string $viewName */
        $viewName = 'pos.index';

        return view($viewName, [
            'sucursal' => $sucursal,
            'almacen' => $almacen,
            'turnoActivo' => $turnoActivo,
        ]);
    }

    public function catalogo(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $empresaId = $this->resolveEmpresaId($user);
        $almacenId = (int) $request->query('almacen_id');

        $productos = Producto::query()
            ->where('empresa_id', $empresaId)
            ->where('activo', true)
            ->select(['id', 'sku', 'codigo_barras', 'nombre', 'precio_base', 'costo_referencia'])
            ->get()
            ->map(function (Producto $prod) use ($almacenId) {
                /** @var ExistenciaAlmacen|null $existencia */
                $existencia = ExistenciaAlmacen::query()
                    ->where('almacen_id', $almacenId)
                    ->where('producto_id', $prod->id)
                    ->first();

                $cantidad = $existencia !== null ? (float) $existencia->cantidad : 0.0;
                $reservada = $existencia !== null ? (float) $existencia->cantidad_reservada : 0.0;
                $disponible = max(0.0, $cantidad - $reservada);

                return [
                    'id' => $prod->id,
                    'sku' => $prod->sku,
                    'codigo_barras' => $prod->codigo_barras,
                    'nombre' => $prod->nombre,
                    'precio' => (float) $prod->precio_base,
                    'stock_disponible' => $disponible,
                ];
            });

        return response()->json($productos);
    }

    public function aperturaCaja(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $empresaId = $this->resolveEmpresaId($user);

        $validated = $request->validate([
            'sucursal_id' => ['required', 'integer'],
            'monto_inicial' => ['required', 'numeric', 'min:0'],
            'observaciones' => ['nullable', 'string', 'max:500'],
        ]);

        $turnoExistente = CajaTurno::query()
            ->where('empresa_id', $empresaId)
            ->where('sucursal_id', $validated['sucursal_id'])
            ->where('user_id', $user->id)
            ->where('estado', 'abierto')
            ->first();

        if ($turnoExistente !== null) {
            return response()->json(['message' => 'Ya existe un turno de caja abierto para este usuario.'], 422);
        }

        $turno = CajaTurno::query()->create([
            'empresa_id' => $empresaId,
            'sucursal_id' => $validated['sucursal_id'],
            'user_id' => $user->id,
            'apertura_at' => now(),
            'monto_inicial' => $validated['monto_inicial'],
            'monto_esperado_efectivo' => $validated['monto_inicial'],
            'estado' => 'abierto',
            'observaciones_apertura' => $validated['observaciones'] ?? null,
        ]);

        return response()->json(['message' => 'Caja abierta con éxito.', 'turno' => $turno]);
    }

    public function cierreCaja(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $empresaId = $this->resolveEmpresaId($user);

        $validated = $request->validate([
            'caja_turno_id' => ['required', 'integer'],
            'monto_real_efectivo' => ['required', 'numeric', 'min:0'],
            'observaciones' => ['nullable', 'string', 'max:500'],
        ]);

        /** @var CajaTurno $turno */
        $turno = CajaTurno::query()
            ->where('empresa_id', $empresaId)
            ->where('user_id', $user->id)
            ->where('estado', 'abierto')
            ->findOrFail($validated['caja_turno_id']);

        $montoReal = (float) $validated['monto_real_efectivo'];
        $montoEsperado = (float) $turno->monto_esperado_efectivo;
        $diferencia = round($montoReal - $montoEsperado, 2);

        $turno->update([
            'cierre_at' => now(),
            'monto_real_efectivo' => $montoReal,
            'diferencia_efectivo' => $diferencia,
            'estado' => 'cerrado',
            'observaciones_cierre' => $validated['observaciones'] ?? null,
        ]);

        return response()->json(['message' => 'Turno de caja cerrado correctamente.', 'turno' => $turno]);
    }

    public function storeVenta(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $empresaId = $this->resolveEmpresaId($user);

        /** @var array{
         *     uuid?: string,
         *     sucursal_id: int,
         *     almacen_id: int,
         *     caja_turno_id?: int|null,
         *     cliente_id?: int|null,
         *     cliente_nombre?: string,
         *     cliente_rfc?: string,
         *     forma_pago_sat?: string,
         *     notas?: string|null,
         *     lineas: array<int, array{
         *         uuid?: string,
         *         producto_id: int,
         *         cantidad: float|int|numeric-string,
         *         precio_unitario: float|int|numeric-string,
         *         descuento_unitario?: float|int|numeric-string,
         *         tasa_iva?: float|int|numeric-string
         *     }>,
         *     pagos: array<int, array{
         *         uuid?: string,
         *         forma_pago_sat: string,
         *         monto: float|int|numeric-string,
         *         monto_recibido?: float|int|numeric-string,
         *         cambio_devuelto?: float|int|numeric-string,
         *         referencia_bancaria?: string|null
         *     }>
         * } $validated */
        $validated = $request->validate([
            'uuid' => ['nullable', 'string', 'size:36'],
            'sucursal_id' => ['required', 'integer'],
            'almacen_id' => ['required', 'integer'],
            'caja_turno_id' => ['nullable', 'integer'],
            'cliente_id' => ['nullable', 'integer'],
            'cliente_nombre' => ['nullable', 'string', 'max:150'],
            'cliente_rfc' => ['nullable', 'string', 'max:13'],
            'forma_pago_sat' => ['nullable', 'string', 'max:5'],
            'notas' => ['nullable', 'string', 'max:500'],
            'lineas' => ['required', 'array', 'min:1'],
            'lineas.*.producto_id' => ['required', 'integer'],
            'lineas.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'lineas.*.precio_unitario' => ['required', 'numeric', 'min:0'],
            'lineas.*.descuento_unitario' => ['nullable', 'numeric', 'min:0'],
            'lineas.*.tasa_iva' => ['nullable', 'numeric', 'min:0'],
            'pagos' => ['required', 'array', 'min:1'],
            'pagos.*.forma_pago_sat' => ['required', 'string', 'max:5'],
            'pagos.*.monto' => ['required', 'numeric', 'gt:0'],
            'pagos.*.monto_recibido' => ['nullable', 'numeric', 'min:0'],
            'pagos.*.cambio_devuelto' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $payload = array_merge($validated, [
                'empresa_id' => $empresaId,
                'user_id' => (int) $user->id,
                'origen_registro' => 'online',
            ]);

            $venta = $this->ventaService->ejecutar($payload);

            return response()->json([
                'message' => 'Venta procesada exitosamente.',
                'folio' => $venta->folio,
                'total' => $venta->total,
                'venta_id' => $venta->id,
            ]);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function sincronizarOffline(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $empresaId = $this->resolveEmpresaId($user);

        /** @var array{
         *     ventas: array<int, array{
         *         uuid: string,
         *         sucursal_id: int,
         *         almacen_id: int,
         *         caja_turno_id?: int|null,
         *         cliente_id?: int|null,
         *         cliente_nombre?: string,
         *         cliente_rfc?: string,
         *         forma_pago_sat?: string,
         *         notas?: string|null,
         *         lineas: array<int, array{
         *             uuid?: string,
         *             producto_id: int,
         *             cantidad: float|int|numeric-string,
         *             precio_unitario: float|int|numeric-string,
         *             descuento_unitario?: float|int|numeric-string,
         *             tasa_iva?: float|int|numeric-string
         *         }>,
         *         pagos: array<int, array{
         *             uuid?: string,
         *             forma_pago_sat: string,
         *             monto: float|int|numeric-string,
         *             monto_recibido?: float|int|numeric-string,
         *             cambio_devuelto?: float|int|numeric-string,
         *             referencia_bancaria?: string|null
         *         }>
         *     }>
         * } $validated */
        $validated = $request->validate([
            'ventas' => ['required', 'array'],
            'ventas.*.uuid' => ['required', 'string', 'size:36'],
            'ventas.*.sucursal_id' => ['required', 'integer'],
            'ventas.*.almacen_id' => ['required', 'integer'],
            'ventas.*.lineas' => ['required', 'array', 'min:1'],
            'ventas.*.pagos' => ['required', 'array', 'min:1'],
        ]);

        $sincronizadas = [];
        $errores = [];

        foreach ($validated['ventas'] as $ventaData) {
            try {
                $payload = array_merge($ventaData, [
                    'empresa_id' => $empresaId,
                    'user_id' => (int) $user->id,
                    'origen_registro' => 'offline',
                ]);

                $venta = $this->ventaService->ejecutar($payload);
                $sincronizadas[] = [
                    'uuid' => $venta->uuid,
                    'folio' => $venta->folio,
                ];
            } catch (Throwable $e) {
                $errores[] = [
                    'uuid' => $ventaData['uuid'],
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'message' => 'Lote offline procesado.',
            'sincronizadas' => $sincronizadas,
            'errores' => $errores,
        ]);
    }

    /**
     * Obtiene de forma segura el ID de la empresa del usuario autenticado.
     */
    private function resolveEmpresaId(User $user): int
    {
        /** @var int|string|null $empresaId */
        $empresaId = $user->getAttribute('empresa_id');

        if (! empty($empresaId)) {
            return (int) $empresaId;
        }

        // Fallback: Tomar la primera empresa activa de la base de datos
        $idEmpresa = Empresa::query()->value('id');

        return $idEmpresa !== null ? (int) $idEmpresa : 1;
    }

    public function ticket(Request $request, Venta $venta): View
    {
        /** @var User $user */
        $user = $request->user();
        $empresaId = $this->resolveEmpresaId($user);

        if ((int) $venta->empresa_id !== $empresaId) {
            abort(403, 'No autorizado para ver este ticket.');
        }

        $venta->load(['detalles', 'pagos', 'sucursal', 'user']);

        /** @var view-string $viewName */
        $viewName = 'pos.ticket';

        return view($viewName, [
            'venta' => $venta,
            'empresa' => $venta->empresa,
        ]);
    }

    /**
     * Devuelve las últimas ventas realizadas en el turno activo para reimpresión.
     */
    public function ultimasVentas(Request $request): JsonResponse
    {
        $user = $request->user();
        $sucursalId = $request->input('sucursal_id');

        $turno = CajaTurno::query()
            ->where('sucursal_id', $sucursalId)
            ->where('estado', 'abierto')
            ->latest('id')
            ->first();

        if (! $turno) {
            return response()->json([]);
        }

        $ventas = Venta::query()
            ->where('caja_turno_id', $turno->id)
            ->with(['detalles'])
            ->latest('id')
            ->take(15)
            ->get(['id', 'uuid', 'folio', 'cliente_nombre', 'total', 'created_at']);

        return response()->json($ventas);
    }

    /**
     * Renderiza la vista del ticket térmico para una venta existente (reimpresión).
     */
    public function reimprimirTicket(string $uuid): View
    {
        $venta = Venta::query()
            ->with(['detalles', 'pagos', 'sucursal.empresa', 'user', 'almacen'])
            ->where('uuid', $uuid)
            ->orWhere('id', $uuid)
            ->firstOrFail();

        return view('pos.ticket', compact('venta'));
    }

    public function devolucion(Request $request, ProcesarDevolucionPosService $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $empresaId = $this->resolveEmpresaId($user);

        /** @var array{
         *     venta_folio: string,
         *     motivo: string,
         *     almacen_id: int,
         *     caja_turno_id?: int|null,
         *     detalles: array<int, array{detalle_id: int, cantidad: float|int|numeric-string}>
         * } $validated */
        $validated = $request->validate([
            'venta_folio' => ['required', 'string'],
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
            'almacen_id' => ['required', 'integer'],
            'caja_turno_id' => ['nullable', 'integer'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.detalle_id' => ['required', 'integer'],
            'detalles.*.cantidad' => ['required', 'numeric', 'gt:0'],
        ]);

        /** @var Almacen $almacen */
        $almacen = Almacen::query()
            ->where('empresa_id', $empresaId)
            ->findOrFail($validated['almacen_id']);

        /** @var CajaTurno|null $turno */
        $turno = null;
        if (! empty($validated['caja_turno_id'])) {
            $turno = CajaTurno::query()->find($validated['caja_turno_id']);
        }

        try {
            $venta = $service->ejecutar($validated, $user, $almacen, $turno);

            return response()->json([
                'message' => "Devolución de la venta {$venta->folio} procesada exitosamente.",
                'folio' => $venta->folio,
            ]);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
