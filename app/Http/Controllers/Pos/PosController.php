<?php

namespace App\Http\Controllers\Pos;

// ---------------------------------------------------------
// IMPORTACIONES DE CONTROLADORES, MODELOS Y SERVICIOS
// ---------------------------------------------------------
use App\Http\Controllers\Controller;
use App\Models\Almacen;
use App\Models\CajaMovimiento;
use App\Models\CajaTurno;
use App\Models\Cliente;
use App\Models\ClienteMonederoMovimiento;
use App\Models\Comanda;
use App\Models\ComandaItem;
use App\Models\Empresa;
use App\Models\ExistenciaAlmacen;
use App\Models\Producto;
use App\Models\Promocion;
use App\Models\RestauranteArea;
use App\Models\RestauranteMesa;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Models\VentaPago;
use App\Services\Marketing\CalcularPromocionesService;
use App\Services\Pos\ProcesarDevolucionPosService;
use App\Services\Pos\RegistrarMovimientoCajaService;
use App\Services\Pos\RegistrarVentaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class PosController extends Controller
{
    /**
     * Constructor con inyección de dependencias para el servicio de ventas.
     */
    public function __construct(
        protected RegistrarVentaService $ventaService
    ) {}

    // =========================================================================
    // SECCIÓN 1: INTERFAZ PRINCIPAL DEL PUNTO DE VENTA Y PLANO DE MESAS
    // =========================================================================

    /**
     * Carga la terminal principal del POS, resolviendo sucursal, almacén, turno y entorno de restaurante.
     */
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        
        // 1. Resolver la empresa correcta asegurando el giro de restaurantes
        $empresa = Empresa::query()->where('giro_comercial', 'restaurantes_alimentos')->first()
            ?? $user->empresa 
            ?? Empresa::query()->first();

        $empresaId = $empresa !== null ? (int) $empresa->id : 3;

        // Contexto de negocio: Detectar si opera como restaurante de forma robusta
        $esRestaurante = false;
        if ($empresa !== null && $empresa->giro_comercial === 'restaurantes_alimentos') {
            $esRestaurante = true;
        }

        // Áreas y zonas de mesas para el plano interactivo
        $areas = [];
        if ($esRestaurante && class_exists(RestauranteArea::class)) {
            $areas = RestauranteArea::query()
                ->where('empresa_id', $empresaId)
                ->where('activo', true)
                ->with('mesas')
                ->get();
        }

        // 2. Resolver sucursal asignada o fallback a la primera activa de la empresa
        /** @var Sucursal|null $sucursal */
        $sucursal = $user->sucursales()->where('sucursales.activo', true)->first()
            ?? Sucursal::query()->where('empresa_id', $empresaId)->where('activo', true)->first()
            ?? Sucursal::query()->first();

        $sucursalId = $sucursal !== null ? (int) $sucursal->id : 1;

        // 3. Resolver almacén físico de la sucursal
        /** @var Almacen|null $almacen */
        $almacen = Almacen::query()
            ->where('empresa_id', $empresaId)
            ->where('sucursal_id', $sucursalId)
            ->where('activo', true)
            ->where('permite_existencias', true)
            ->first()
            ?? Almacen::query()->where('empresa_id', $empresaId)->first()
            ?? Almacen::query()->first();

        // 4. Buscar turno abierto para el cajero en esta sucursal
        /** @var CajaTurno|null $turnoActivo */
        $turnoActivo = CajaTurno::query()
            ->where('empresa_id', $empresaId)
            ->where('sucursal_id', $sucursalId)
            ->where('user_id', $user->id)
            ->where('estado', 'abierto')
            ->latest('id')
            ->first();

        /** @var view-string $viewName */
        $viewName = 'pos.index';

        return view($viewName, [
            'sucursal' => $sucursal,
            'almacen' => $almacen,
            'turnoActivo' => $turnoActivo,
            'areasMesas' => $areas,
            'esRestaurante' => $esRestaurante,
        ]);
    }

    // =========================================================================
    // SECCIÓN 2: CATÁLOGO DE PRODUCTOS, CLIENTES LEALTAD Y TURNOS DE CAJA
    // =========================================================================

    /**
     * Entrega el catálogo reactivo de productos con existencias físicas en el almacén seleccionado.
     */
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

    /**
     * Busca clientes registrados para aplicar promociones VIP, puntos o monedero en POS.
     */
    public function buscarClientesLealtad(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $empresaId = Empresa::query()->where('id', 3)->value('id') ?? 3;

        $clientes = Cliente::query()
            ->where('empresa_id', $empresaId)
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('nombre', 'like', "%{$q}%")
                        ->orWhere('telefono_whatsapp', 'like', "%{$q}%")
                        ->orWhere('rfc', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('puntos_acumulados')
            ->limit(15)
            ->get(['id', 'nombre', 'rfc', 'telefono_whatsapp', 'nivel_lealtad', 'puntos_acumulados', 'saldo_monedero']);

        return response()->json($clientes);
    }

    /**
     * Abre un nuevo turno de caja con fondo inicial en efectivo.
     */
    public function aperturaCaja(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $empresa = Empresa::query()->where('id', 3)->first()
            ?? Empresa::query()->where('giro_comercial', 'restaurantes_alimentos')->first();

        $empresaId = $empresa !== null ? (int) $empresa->id : $this->resolveEmpresaId($user);

        $validated = $request->validate([
            'sucursal_id' => ['required', 'integer'],
            'monto_inicial' => ['required', 'numeric', 'min:0'],
            'observaciones' => ['nullable', 'string', 'max:500'],
        ]);

        $turnoExistente = CajaTurno::query()
            ->where('sucursal_id', $validated['sucursal_id'])
            ->where('user_id', $user->id)
            ->where('estado', 'abierto')
            ->first();

        if ($turnoExistente !== null) {
            return response()->json([
                'message' => 'Ya existe un turno de caja abierto para este usuario.',
            ], 422);
        }

        $turno = CajaTurno::query()->create([
            'empresa_id' => $empresaId,
            'sucursal_id' => $validated['sucursal_id'],
            'user_id' => $user->id,
            'apertura_at' => now(),
            'monto_inicial' => $validated['monto_inicial'],
            'total_ventas_efectivo' => 0.00,
            'total_ventas_tarjeta' => 0.00,
            'total_ventas_transferencia' => 0.00,
            'total_retiros' => 0.00,
            'total_otros_ingresos' => 0.00,
            'monto_esperado_efectivo' => $validated['monto_inicial'],
            'estado' => 'abierto',
            'observaciones_apertura' => $validated['observaciones'] ?? null,
        ]);

        return response()->json([
            'message' => 'Caja abierta con éxito.',
            'turno' => $turno,
        ]);
    }

    /**
     * Cierra el turno de caja activo calculando la diferencia de efectivo.
     */
    public function cierreCaja(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'caja_turno_id' => ['required', 'integer'],
            'monto_real_efectivo' => ['required', 'numeric', 'min:0'],
            'observaciones' => ['nullable', 'string', 'max:500'],
        ]);

        /** @var CajaTurno $turno */
        $turno = CajaTurno::query()
            ->where('user_id', $user->id)
            ->where('estado', 'abierto')
            ->findOrFail($validated['caja_turno_id']);

        $desglose = $this->calcularDesgloseTurno($turno);

        $montoReal = (float) $validated['monto_real_efectivo'];
        $montoEsperado = (float) $desglose['efectivo_esperado'];
        $diferencia = round($montoReal - $montoEsperado, 2);

        $turno->update([
            'cierre_at' => now(),
            'total_ventas_efectivo' => $desglose['ventas_efectivo'],
            'total_ventas_tarjeta' => $desglose['ventas_tarjeta'],
            'total_ventas_transferencia' => $desglose['ventas_transferencia'],
            'total_retiros' => $desglose['total_retiros'],
            'total_otros_ingresos' => $desglose['total_ingresos'],
            'monto_esperado_efectivo' => $montoEsperado,
            'monto_real_efectivo' => $montoReal,
            'diferencia_efectivo' => $diferencia,
            'estado' => 'cerrado',
            'observaciones_cierre' => $validated['observaciones'] ?? null,
        ]);

        return response()->json([
            'message' => 'Turno de caja cerrado correctamente.',
            'turno' => $turno->fresh(),
        ]);
    }

    // =========================================================================
    // SECCIÓN 3: PROCESAMIENTO DE COBROS, PROMOCIONES, MONEDERO Y SPLIT BILL
    // =========================================================================

    /**
     * Procesa la venta directa en línea, aplicando descuentos, monedero, lealtad y liberando mesas de restaurante.
     */
    public function storeVenta(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $empresa = Empresa::query()->where('id', 3)->first()
            ?? Empresa::query()->where('giro_comercial', 'restaurantes_alimentos')->first()
            ?? Empresa::query()->first();

        $empresaId = $empresa !== null ? (int) $empresa->id : 3;

        $sucursal = $user->sucursales()
            ->where('sucursales.empresa_id', $empresaId)
            ->where('sucursales.activo', true)
            ->first()
            ?? Sucursal::query()->where('empresa_id', $empresaId)->where('codigo', 'REST-01')->first()
            ?? Sucursal::query()->first();

        $sucursalId = $sucursal !== null ? (int) $sucursal->id : 2;

        $almacen = Almacen::query()
            ->where('empresa_id', $empresaId)
            ->where('sucursal_id', $sucursalId)
            ->where('activo', true)
            ->first()
            ?? Almacen::query()->where('empresa_id', $empresaId)->where('activo', true)->first()
            ?? Almacen::query()->first();

        $almacenId = $almacen !== null ? (int) $almacen->id : 1;

        $validated = $request->validate([
            'uuid' => ['nullable', 'string', 'size:36'],
            'sucursal_id' => ['nullable', 'integer'],
            'almacen_id' => ['nullable', 'integer'],
            'caja_turno_id' => ['nullable', 'integer'],
            'cliente_id' => ['nullable', 'integer'],
            'cliente_nombre' => ['nullable', 'string', 'max:150'],
            'cliente_rfc' => ['nullable', 'string', 'max:13'],
            'forma_pago_sat' => ['nullable', 'string', 'max:5'],
            'notas' => ['nullable', 'string', 'max:500'],
            'comanda_ids' => ['nullable', 'array'],
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
            'pagos.*.referencia_bancaria' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $descuentoPromos = (float) $request->input('descuento_promociones', 0);
            $montoMonederoUsado = (float) $request->input('monto_monedero_usado', 0);
            $promosDetalle = $request->input('promociones_aplicadas_detalle', []);
            $promocionesAplicadasIds = $request->input('promociones_aplicadas_ids', []);

            $etiquetasCompactas = [];
            foreach ($promosDetalle as $pDet) {
                $pId = (int) ($pDet['id'] ?? 0);
                $pAhorro = number_format((float) ($pDet['ahorro'] ?? 0), 2, '.', '');
                if ($pId > 0 && (float) $pAhorro > 0) {
                    $etiquetasCompactas[] = "[P:{$pId}:{$pAhorro}]";
                }
            }
            if ($montoMonederoUsado > 0) {
                $mUsado = number_format($montoMonederoUsado, 2, '.', '');
                $etiquetasCompactas[] = "[M:{$mUsado}]";
            }

            $sufijoPromos = ! empty($etiquetasCompactas) ? ' '.implode(' ', $etiquetasCompactas) : '';
            $notasVenta = mb_substr(trim(($validated['notas'] ?? '').$sufijoPromos), 0, 250);

            $payload = array_merge($validated, [
                'empresa_id' => (int) $empresaId,
                'sucursal_id' => (int) ($validated['sucursal_id'] ?? $sucursalId),
                'almacen_id' => (int) ($validated['almacen_id'] ?? $almacenId),
                'user_id' => (int) $user->id,
                'origen_registro' => 'online',
                'descuento_total' => round($descuentoPromos + $montoMonederoUsado, 2),
                'notas' => $notasVenta !== '' ? $notasVenta : null,
            ]);

            $venta = $this->ventaService->ejecutar($payload);

            if ($notasVenta !== '' && $venta->notas !== $notasVenta) {
                $venta->update(['notas' => $notasVenta]);
            }

            if (! empty($promocionesAplicadasIds) && is_array($promocionesAplicadasIds)) {
                Promocion::query()
                    ->whereIn('id', $promocionesAplicadasIds)
                    ->increment('usos_actuales');
            }

            $clienteId = $request->integer('cliente_id');
            if ($clienteId > 0) {
                $cliente = Cliente::query()->find($clienteId);
                if ($cliente !== null) {
                    if ($montoMonederoUsado > 0) {
                        $descuentoReal = min((float) $cliente->saldo_monedero, $montoMonederoUsado);
                        if ($descuentoReal > 0) {
                            $cliente->decrement('saldo_monedero', $descuentoReal);

                            ClienteMonederoMovimiento::query()->create([
                                'empresa_id' => $empresaId,
                                'cliente_id' => $cliente->id,
                                'venta_id' => $venta->id,
                                'tipo' => 'canje_pago',
                                'monto_monedero' => -$descuentoReal,
                                'puntos' => 0,
                                'concepto' => "Canje en Ticket {$venta->folio}",
                            ]);
                        }
                    }

                    $cashbackGanado = round((float) $venta->total * 0.05, 2);
                    $puntosGanados = (int) floor((float) $venta->total / 10);

                    if ($cashbackGanado > 0 || $puntosGanados > 0) {
                        $cliente->increment('saldo_monedero', $cashbackGanado);
                        $cliente->increment('puntos_acumulados', $puntosGanados);

                        if ($cliente->nivel_lealtad === 'regular' && ($cliente->puntos_acumulados + $puntosGanados) >= 500) {
                            $cliente->update(['nivel_lealtad' => 'frecuente']);
                        }

                        ClienteMonederoMovimiento::query()->create([
                            'empresa_id' => $empresaId,
                            'cliente_id' => $cliente->id,
                            'venta_id' => $venta->id,
                            'tipo' => 'abono_compra',
                            'monto_monedero' => $cashbackGanado,
                            'puntos' => $puntosGanados,
                            'concepto' => "Acumulación por compra {$venta->folio} (5% Cashback)",
                        ]);
                    }
                }
            }

            $comandaIds = $request->input('comanda_ids', []);
            $esSubcuenta = $request->boolean('es_subcuenta', false);

            if (! empty($comandaIds) && is_array($comandaIds)) {
                $comandas = Comanda::query()->whereIn('id', $comandaIds)->get();

                if (! $esSubcuenta) {
                    $nombresMesas = $comandas->pluck('mesa_o_referencia')->filter()->unique()->all();

                    Comanda::query()
                        ->whereIn('id', $comandaIds)
                        ->update([
                            'venta_id' => $venta->id,
                            'estado' => 'completada',
                            'finalizada_at' => now(),
                        ]);

                    RestauranteMesa::query()
                        ->where(function ($q) use ($comandaIds, $nombresMesas) {
                            $q->whereIn('comanda_actual_id', $comandaIds);
                            if (! empty($nombresMesas)) {
                                $q->orWhereIn('numero_o_nombre', $nombresMesas);
                            }
                        })
                        ->update([
                            'estado' => 'disponible',
                            'comanda_actual_id' => null,
                        ]);
                } else {
                    $itemIdsPagados = $request->input('comanda_item_ids_pagados', []);

                    if (! empty($itemIdsPagados) && is_array($itemIdsPagados)) {
                        ComandaItem::query()
                            ->whereIn('id', $itemIdsPagados)
                            ->update([
                                'estado' => 'listo',
                                'notas_especiales' => DB::raw("CONCAT(COALESCE(notas_especiales, ''), ' [PAGADO-VTA:{$venta->id}]')"),
                            ]);

                        $itemsSinPagar = ComandaItem::query()
                            ->whereIn('comanda_id', $comandaIds)
                            ->where(function ($q) {
                                $q->whereNull('notas_especiales')
                                    ->orWhere('notas_especiales', 'not like', '%[PAGADO-VTA:%');
                            })
                            ->count();

                        $mesaLiquidada = ($itemsSinPagar === 0);
                    } else {
                        foreach ($comandas as $cmd) {
                            $notaActual = (string) ($cmd->notas_generales ?? '');
                            $cmd->update([
                                'notas_generales' => trim($notaActual." [ABONO:{$venta->total} VTA:{$venta->id}]"),
                            ]);
                        }

                        $totalConsumo = 0.0;
                        $totalAbonado = 0.0;

                        foreach ($comandas->fresh() as $cmd) {
                            foreach ($cmd->items as $it) {
                                $totalConsumo += ((float) $it->cantidad) * ((float) ($it->producto->precio_base ?? 0));
                            }
                            preg_match_all('/\[ABONO:([0-9.]+)/', (string) $cmd->notas_generales, $matches);
                            if (! empty($matches[1])) {
                                $totalAbonado += array_sum(array_map('floatval', $matches[1]));
                            }
                        }

                        $mesaLiquidada = ($totalAbonado >= ($totalConsumo - 0.50));
                    }

                    if ($mesaLiquidada) {
                        $nombresMesas = $comandas->pluck('mesa_o_referencia')->filter()->unique()->all();

                        Comanda::query()
                            ->whereIn('id', $comandaIds)
                            ->update([
                                'venta_id' => $venta->id,
                                'estado' => 'completada',
                                'finalizada_at' => now(),
                            ]);

                        RestauranteMesa::query()
                            ->where(function ($q) use ($comandaIds, $nombresMesas) {
                                $q->whereIn('comanda_actual_id', $comandaIds);
                                if (! empty($nombresMesas)) {
                                    $q->orWhereIn('numero_o_nombre', $nombresMesas);
                                }
                            })
                            ->update([
                                'estado' => 'disponible',
                                'comanda_actual_id' => null,
                            ]);
                    }
                }
            }

            return response()->json([
                'message' => 'Venta procesada exitosamente.',
                'folio' => $venta->folio,
                'total' => $venta->total,
                'venta_id' => $venta->id,
            ]);
        } catch (Throwable $e) {
            return response()->json(['message' => 'Error al procesar cobro: '.$e->getMessage()], 422);
        }
    }

    // =========================================================================
    // SECCIÓN 4: SINCRONIZACIÓN OFFLINE, TICKETS Y DEVOLUCIONES
    // =========================================================================

    /**
     * Sincroniza ventas realizadas sin conexión (Offline-First).
     */
    public function sincronizarOffline(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $empresaId = $this->resolveEmpresaId($user);

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

        /** @var Empresa|null $empresa */
        $empresa = $user->empresa ?? Empresa::query()->where('activo', true)->first() ?? Empresa::query()->first();

        return $empresa !== null ? (int) $empresa->id : 1;
    }

    /**
     * Renderiza el ticket térmico de una venta.
     */
    public function ticket(Request $request, Venta $venta): View
    {
        /** @var User $user */
        $user = $request->user();
        $empresaId = $this->resolveEmpresaId($user);

        if ((int) $venta->empresa_id !== $empresaId) {
            abort(403, 'No autorizado para ver este ticket.');
        }

        $venta->load(['detalles', 'pagos', 'sucursal', 'user']);

        return view('pos.ticket', [
            'venta' => $venta,
            'empresa' => $venta->empresa,
        ]);
    }

    /**
     * Retorna las últimas ventas completadas.
     */
    public function ultimasVentas(Request $request): JsonResponse
    {
        $sucursalId = $request->integer('sucursal_id');

        $ventas = Venta::query()
            ->when($sucursalId > 0, fn ($q) => $q->where('sucursal_id', $sucursalId))
            ->where('estado', 'completada')
            ->latest('id')
            ->take(15)
            ->get([
                'id',
                'uuid',
                'folio',
                'cliente_nombre',
                'cliente_rfc',
                'total',
                'estado_facturacion',
                'uuid_fiscal',
                'serie_folio_factura',
                'created_at',
            ]);

        return response()->json($ventas);
    }

    /**
     * Reimprime un ticket por su UUID o ID.
     */
    public function reimprimirTicket(string $uuid): View
    {
        $venta = Venta::query()
            ->with(['detalles', 'pagos', 'sucursal.empresa', 'user', 'almacen'])
            ->where(function ($q) use ($uuid) {
                if (ctype_digit($uuid)) {
                    $q->where('id', (int) $uuid);
                } else {
                    $q->where('uuid', $uuid);
                }
            })
            ->firstOrFail();

        return view('pos.ticket', [
            'venta' => $venta,
            'empresa' => $venta->empresa,
        ]);
    }

    /**
     * Procesa una devolución de mercancía con reingreso al almacén.
     */
    public function devolucion(Request $request, ProcesarDevolucionPosService $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $empresaId = $this->resolveEmpresaId($user);

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

    // =========================================================================
    // SECCIÓN 5: CONSULTA DE COMANDAS ABIERTAS Y PRECUENTA TÉRMICA (RESTAURANTE)
    // =========================================================================

    /**
     * Lista todas las mesas con comandas activas y saldos pendientes.
     */
    public function comandasAbiertasRestaurante(Request $request): JsonResponse
    {
        $empresa = Empresa::query()->where('id', 3)->first()
            ?? Empresa::query()->where('giro_comercial', 'restaurantes_alimentos')->first();

        $empresaId = $empresa !== null ? (int) $empresa->id : 3;

        $comandas = Comanda::query()
            ->where('empresa_id', $empresaId)
            ->whereNull('venta_id')
            ->where('estado', '!=', 'cancelada')
            ->with(['items.producto', 'mesero:id,name'])
            ->orderBy('id', 'asc')
            ->get();

        $mesas = [];
        foreach ($comandas as $cmd) {
            $nombreMesa = $cmd->mesa_o_referencia;
            if (! isset($mesas[$nombreMesa])) {
                $mesas[$nombreMesa] = [
                    'mesa' => $nombreMesa,
                    'comanda_ids' => [],
                    'folios' => [],
                    'mesero' => $cmd->mesero->name ?? 'Mesero',
                    'cuenta_pedida' => false,
                    'total' => 0.0,
                    'items' => [],
                ];
            }

            $mesas[$nombreMesa]['comanda_ids'][] = $cmd->id;
            $mesas[$nombreMesa]['folios'][] = $cmd->folio;

            if (str_contains((string) ($cmd->notas_generales ?? ''), 'CUENTA PEDIDA')) {
                $mesas[$nombreMesa]['cuenta_pedida'] = true;
            }

            foreach ($cmd->items as $item) {
                if (str_contains((string) ($item->notas_especiales ?? ''), '[PAGADO-VTA:')) {
                    continue;
                }

                $precio = (float) ($item->producto->precio_base ?? 0);
                $cant = (float) $item->cantidad;
                $sub = $precio * $cant;
                $mesas[$nombreMesa]['total'] += $sub;

                $mesas[$nombreMesa]['items'][] = [
                    'id' => $item->id,
                    'producto_id' => $item->producto_id,
                    'sku' => $item->producto->sku ?? 'CMD',
                    'nombre' => $item->producto->nombre ?? 'Platillo',
                    'cantidad' => $cant,
                    'precio_unitario' => $precio,
                    'subtotal' => $sub,
                    'notas' => $item->notas_especiales,
                ];
            }
        }

        foreach ($mesas as &$m) {
            $abonosPrevios = 0.0;
            $cmdsMesa = Comanda::query()->whereIn('id', $m['comanda_ids'])->get();
            foreach ($cmdsMesa as $cm) {
                preg_match_all('/\[ABONO:([0-9.]+)/', (string) $cm->notas_generales, $matches);
                if (! empty($matches[1])) {
                    $abonosPrevios += array_sum(array_map('floatval', $matches[1]));
                }
            }

            $m['total'] = max(0.0, round($m['total'] - $abonosPrevios, 2));
            $m['folio'] = implode(', ', array_unique($m['folios']));
        }

        $mesasPendientes = array_filter(array_values($mesas), fn ($m) => count($m['items']) > 0 && $m['total'] > 0.00);

        return response()->json(array_values($mesasPendientes));
    }

    /**
     * Imprime la Precuenta Térmica consolidando el consumo activo y evaluando promociones.
     */
    public function imprimirPrecuentaMesa(Request $request, string $mesa): View
    {
        $empresa = Empresa::query()->where('id', 3)->first()
            ?? Empresa::query()->where('giro_comercial', 'restaurantes_alimentos')->first();

        $empresaId = $empresa !== null ? (int) $empresa->id : 3;

        $comandas = Comanda::query()
            ->where('empresa_id', $empresaId)
            ->where('mesa_o_referencia', $mesa)
            ->whereNull('venta_id')
            ->where('estado', '!=', 'cancelada')
            ->with(['items.producto', 'mesero', 'empresa', 'sucursal'])
            ->get();

        abort_if($comandas->isEmpty(), 404, 'No hay comandas activas para esta mesa.');

        $primeraComanda = $comandas->first();
        $itemsConsolidados = [];
        $itemsParaPromos = [];
        $totalBruto = 0.0;

        foreach ($comandas as $cmd) {
            foreach ($cmd->items as $item) {
                if (str_contains((string) ($item->notas_especiales ?? ''), '[PAGADO-VTA:')) {
                    continue;
                }

                $precio = (float) ($item->producto->precio_base ?? 0);
                $cantidad = (float) $item->cantidad;
                $subtotal = $precio * $cantidad;
                $totalBruto += $subtotal;

                $itemsConsolidados[] = $item;
                $itemsParaPromos[] = [
                    'producto_id' => (int) $item->producto_id,
                    'cantidad' => $cantidad,
                    'precio_unitario' => $precio,
                ];
            }
        }

        $evaluacionPromos = app(CalcularPromocionesService::class)
            ->evaluar($empresaId, $itemsParaPromos);

        $descuentoPromos = (float) $evaluacionPromos['descuento_total'];
        $totalConsumo = max(0, round($totalBruto - $descuentoPromos, 2));

        return view('restaurante.mesas.precuenta_ticket', [
            'mesa' => (object) ['numero_o_nombre' => $mesa],
            'comanda' => $primeraComanda,
            'itemsConsolidados' => $itemsConsolidados,
            'totalBruto' => round($totalBruto, 2),
            'descuentoPromociones' => round($descuentoPromos, 2),
            'promocionesAplicadas' => $evaluacionPromos['promociones'],
            'totalConsumo' => $totalConsumo,
            'tokenFactura' => strtoupper(Str::random(10)),
        ]);
    }

    // =========================================================================
    // SECCIÓN 7: CORTES DE CAJA (CORTE X PARCIAL, RETIROS Y CORTE Z FINAL)
    // =========================================================================

    /**
     * Devuelve el resumen financiero en tiempo real del turno abierto (Corte Parcial / X).
     */
    public function resumenCorteParcial(Request $request, CajaTurno $turno): JsonResponse
    {
        $datos = $this->calcularDesgloseTurno($turno);

        return response()->json($datos);
    }

    /**
     * Registra un retiro parcial de efectivo o ingreso extraordinario en el turno abierto.
     */
    public function registrarMovimientoCaja(Request $request, RegistrarMovimientoCajaService $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'caja_turno_id' => ['required', 'integer', 'exists:caja_turnos,id'],
            'tipo' => ['required', 'in:ingreso,retiro'],
            'concepto' => ['required', 'string', 'max:120'],
            'monto' => ['required', 'numeric', 'gt:0'],
            'motivo' => ['required', 'string', 'max:500'],
            'comprobante_numero' => ['nullable', 'string', 'max:60'],
        ]);

        try {
            $movimiento = $service->ejecutar($validated, $user, $user);
            $turnoActualizado = CajaTurno::query()->find($validated['caja_turno_id']);

            return response()->json([
                'message' => ($validated['tipo'] === 'retiro' ? 'Retiro' : 'Ingreso').' registrado correctamente.',
                'movimiento' => $movimiento,
                'turno' => $turnoActualizado,
            ]);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Renderiza el ticket térmico imprimible tanto para Corte Parcial (X) como Corte Final (Z).
     */
    public function imprimirTicketCorte(Request $request, CajaTurno $turno): View
    {
        $tipoCorte = strtoupper($request->query('tipo', $turno->estado === 'cerrado' ? 'Z' : 'X'));
        $desglose = $this->calcularDesgloseTurno($turno);

        return view('pos.corte_ticket', [
            'turno' => $turno->load(['sucursal.empresa', 'user']),
            'tipoCorte' => $tipoCorte,
            'desglose' => $desglose,
        ]);
    }

    /**
     * Calcula totales por forma de pago, movimientos, promociones y productos vendidos.
     *
     * @return array<string, mixed>
     */
    private function calcularDesgloseTurno(CajaTurno $turno): array
    {
        $ventas = Venta::query()
            ->where('caja_turno_id', $turno->id)
            ->where('estado', 'completada')
            ->with(['pagos', 'detalles'])
            ->get();

        $efectivoVentas = 0.0;
        $tarjetaDebitoCredito = 0.0;
        $transferencia = 0.0;
        $otrosMetodos = 0.0;

        /** @var array<string|int, array{nombre: string|null, cantidad: float, total: float}> $productosAgrupados */
        $productosAgrupados = [];

        $totalDescuentosPromos = 0.0;
        $totalCanjeMonedero = 0.0;
        $promocionesResumen = [];

        foreach ($ventas as $v) {
            $notasVenta = (string) ($v->notas ?? '');
            $descuentoVentaActual = 0.0;

            if (preg_match_all('/\[P:(\d+):([0-9.]+)\]/', $notasVenta, $matchesP, PREG_SET_ORDER)) {
                foreach ($matchesP as $mp) {
                    $promoObj = Promocion::find((int) $mp[1]);
                    $nombrePromo = $promoObj->nombre ?? 'Promoción / Combo';
                    $ahorro = (float) $mp[2];

                    $totalDescuentosPromos += $ahorro;
                    $descuentoVentaActual += $ahorro;

                    if (! isset($promocionesResumen[$nombrePromo])) {
                        $promocionesResumen[$nombrePromo] = [
                            'nombre' => $nombrePromo,
                            'cantidad' => 0,
                            'ahorro_total' => 0.0,
                        ];
                    }
                    $promocionesResumen[$nombrePromo]['cantidad']++;
                    $promocionesResumen[$nombrePromo]['ahorro_total'] += $ahorro;
                }
            }

            if (preg_match('/\[M:([0-9.]+)\]/', $notasVenta, $matchM)) {
                $totalCanjeMonedero += (float) $matchM[1];
            }

            $sumaBrutaV = (float) $v->detalles->sum('total');

            if ($descuentoVentaActual === 0.0 && ($sumaBrutaV - (float) $v->total) > 0.01) {
                $ahorroExtra = round($sumaBrutaV - (float) $v->total, 2);
                $totalDescuentosPromos += $ahorroExtra;
                $nombreFallback = 'Descuento Promoción / Combo';
                if (! isset($promocionesResumen[$nombreFallback])) {
                    $promocionesResumen[$nombreFallback] = [
                        'nombre' => $nombreFallback,
                        'cantidad' => 0,
                        'ahorro_total' => 0.0,
                    ];
                }
                $promocionesResumen[$nombreFallback]['cantidad']++;
                $promocionesResumen[$nombreFallback]['ahorro_total'] += $ahorroExtra;
            }

            foreach ($v->pagos as $p) {
                /** @var VentaPago $p */
                $montoNeto = (float) $p->monto;
                match ($p->forma_pago_sat) {
                    '01' => $efectivoVentas += $montoNeto,
                    '04', '28' => $tarjetaDebitoCredito += $montoNeto,
                    '03' => $transferencia += $montoNeto,
                    default => $otrosMetodos += $montoNeto,
                };
            }

            foreach ($v->detalles as $det) {
                /** @var VentaDetalle $det */
                $key = $det->producto_id ?? $det->descripcion_producto;
                if (! isset($productosAgrupados[$key])) {
                    $productosAgrupados[$key] = [
                        'nombre' => $det->descripcion_producto,
                        'cantidad' => 0.0,
                        'total' => 0.0,
                    ];
                }
                $productosAgrupados[$key]['cantidad'] += (float) $det->cantidad;
                $productosAgrupados[$key]['total'] += (float) $det->total;
            }
        }

        usort($productosAgrupados, fn (array $a, array $b): int => $b['cantidad'] <=> $a['cantidad']);

        $movimientos = CajaMovimiento::query()
            ->where('caja_turno_id', $turno->id)
            ->orderBy('id')
            ->get();

        $totalRetiros = (float) $movimientos->where('tipo', 'retiro')->sum('monto');
        $totalIngresos = (float) $movimientos->where('tipo', 'ingreso')->sum('monto');

        $fondoInicial = (float) $turno->monto_inicial;
        $efectivoEsperado = round($fondoInicial + $efectivoVentas + $totalIngresos - $totalRetiros, 2);

        return [
            'cantidad_tickets' => $ventas->count(),
            'total_vendido' => round($efectivoVentas + $tarjetaDebitoCredito + $transferencia + $otrosMetodos, 2),
            'fondo_inicial' => round($fondoInicial, 2),
            'ventas_efectivo' => round($efectivoVentas, 2),
            'ventas_tarjeta' => round($tarjetaDebitoCredito, 2),
            'ventas_transferencia' => round($transferencia, 2),
            'ventas_otros' => round($otrosMetodos, 2),
            'total_retiros' => round($totalRetiros, 2),
            'total_ingresos' => round($totalIngresos, 2),
            'efectivo_esperado' => $efectivoEsperado,
            'efectivo_contado' => $turno->monto_real_efectivo !== null ? (float) $turno->monto_real_efectivo : null,
            'diferencia' => $turno->diferencia_efectivo !== null ? (float) $turno->diferencia_efectivo : null,
            'movimientos' => $movimientos,
            'productos_vendidos' => $productosAgrupados,
            'total_descuentos_promos' => round($totalDescuentosPromos, 2),
            'total_canje_monedero' => round($totalCanjeMonedero, 2),
            'cantidad_promos_aplicadas' => array_sum(array_column($promocionesResumen, 'cantidad')),
            'promociones_resumen' => array_values($promocionesResumen),
        ];
    }
}
