<?php

namespace App\Http\Controllers\Pos;

// =========================================================================
// IMPORTACIONES DE CONTROLADORES, MODELOS Y SERVICIOS
// =========================================================================
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
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

/**
 * Controlador principal del Punto de Venta (POS).
 * Gestiona el plano/consumo de mesas, cobros, cortes de caja y alertas de precuenta.
 */
class PosController extends Controller
{
    /**
     * Inyección de dependencias del servicio de registro de ventas.
     */
    public function __construct(
        protected RegistrarVentaService $ventaService
    ) {}

    // =========================================================================
    // SECCIÓN 1: INTERFAZ PRINCIPAL DEL PUNTO DE VENTA Y PLANO DE MESAS
    // =========================================================================

    /**
     * Carga la terminal principal del POS resolviendo empresa, sucursal, almacén y turno.
     */
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        // 1. Resolver empresa del giro de restaurantes
        $empresa = Empresa::query()->where('giro_comercial', 'restaurantes_alimentos')->first()
            ?? $user->empresa
            ?? Empresa::query()->first();

        $empresaId = $empresa !== null ? (int) $empresa->id : 3;

        // Detectar si opera como restaurante
        $esRestaurante = $empresa !== null && $empresa->giro_comercial === 'restaurantes_alimentos';

        // 2. Resolver sucursal del restaurante (priorizando REST-01 o sucursal activa de la empresa)
        /** @var Sucursal|null $sucursal */
        $sucursal = Sucursal::query()
            ->where('empresa_id', $empresaId)
            ->where('codigo', 'REST-01')
            ->where('activo', true)
            ->first()
            ?? Sucursal::query()
                ->where('empresa_id', $empresaId)
                ->where('activo', true)
                ->first()
            ?? $user->sucursales()->where('sucursales.activo', true)->first()
            ?? Sucursal::query()->first();

        $sucursalId = $sucursal !== null ? (int) $sucursal->id : 1;

        // 3. Cargar áreas y mesas para el plano interactivo
        $areas = [];
        if ($esRestaurante && class_exists(RestauranteArea::class)) {
            $tieneSucursalId = Schema::hasColumn('restaurante_mesas', 'sucursal_id');

            $areas = RestauranteArea::query()
                ->where('empresa_id', $empresaId)
                ->where('activo', true)
                ->with(['mesas' => function ($q) use ($sucursalId, $tieneSucursalId) {
                    $q->where('activo', true);
                    if ($tieneSucursalId && $sucursalId > 0) {
                        $q->where(function ($sub) use ($sucursalId) {
                            $sub->where('sucursal_id', $sucursalId)->orWhereNull('sucursal_id');
                        });
                    }
                }])
                ->get();
        }

        // 4. Resolver almacén físico correspondiente a la sucursal del restaurante
        /** @var Almacen|null $almacen */
        $almacen = Almacen::query()
            ->where('empresa_id', $empresaId)
            ->where('sucursal_id', $sucursalId)
            ->where('activo', true)
            ->first()
            ?? Almacen::query()
                ->where('empresa_id', $empresaId)
                ->where('activo', true)
                ->first()
            ?? Almacen::query()->first();

        // 5. Buscar turno abierto para el cajero en esta sucursal
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
     * Entrega el catálogo reactivo de productos con existencias físicas en el almacén.
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
     * Busca clientes registrados para el modal CFDI y lealtad en el POS,
     * incluyendo sus datos fiscales SAT (CP, régimen, uso CFDI) y correo.
     */
    public function buscarClientesLealtad(Request $request): JsonResponse
    {
        // Obtener término de búsqueda y empresa activa
        $q = trim((string) $request->query('q', ''));
        $empresaId = Empresa::query()->where('id', 3)->value('id') ?? 3;

        $clientes = Cliente::query()
            ->where('empresa_id', $empresaId)
            ->when($q !== '', function ($query) use ($q): void {
                // Filtrar por nombre, teléfono/WhatsApp, RFC o correo electrónico
                $query->where(function ($sub) use ($q): void {
                    $sub->where('nombre', 'like', "%{$q}%")
                        ->orWhere('telefono_whatsapp', 'like', "%{$q}%")
                        ->orWhere('rfc', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(30)
            ->get([
                'id',
                'nombre',
                'rfc',
                'codigo_postal',
                'regimen_fiscal',
                'uso_cfdi',
                'email',
                'telefono_whatsapp',
                'nivel_lealtad',
                'puntos_acumulados',
                'saldo_monedero',
            ]);

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
     * Procesa la venta directa en línea, aplicando descuentos, monedero y liberando mesas.
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

        $empresaRestauranteId = Empresa::query()
            ->where('giro_comercial', 'restaurantes_alimentos')
            ->value('id');

        $empresasPermitidas = array_filter([
            (int) $this->resolveEmpresaId($user),
            $user->empresa?->id ? (int) $user->empresa->id : null,
            $empresaRestauranteId ? (int) $empresaRestauranteId : null,
        ]);

        $tieneAccesoSucursal = $venta->sucursal_id
            ? $user->sucursales()->where('sucursales.id', $venta->sucursal_id)->exists()
            : false;

        if (! in_array((int) $venta->empresa_id, $empresasPermitidas, true) && ! $tieneAccesoSucursal) {
            abort(403, 'No autorizado para ver este ticket.');
        }

        $venta->load(['detalles', 'pagos', 'sucursal', 'user']);

        return view('pos.ticket', [
            'venta' => $venta,
            'empresa' => $venta->empresa,
        ]);
    }

    /**
     * Retorna los tickets recientes filtrados automáticamente según el rol del usuario:
     * - Administrador, Gerente, Subgerente o Supervisor: todos los tickets del día en la sucursal.
     * - Empleado / Cajero normal: únicamente los tickets del turno de caja abierto actual.
     */
    public function ultimasVentas(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $sucursalId = $request->integer('sucursal_id');
        $cajaTurnoId = $request->integer('caja_turno_id');

        // Detectar si el usuario tiene perfil de supervisión o gerencia (Spatie roles o atributo/nombre)
        $esGerenciaOSupervision = false;

        if (method_exists($user, 'hasAnyRole')) {
            $esGerenciaOSupervision = $user->hasAnyRole([
                'administrador',
                'gerente',
                'subgerente',
                'sub_gerente',
                'supervisor',
            ]);
        }

        if (! $esGerenciaOSupervision) {
            $rolTexto = strtolower((string) ($user->rol ?? $user->role ?? $user->puesto ?? $user->name ?? ''));
            foreach (['administrador', 'gerente', 'subgerente', 'sub gerente', 'supervisor'] as $palabraClave) {
                if (str_contains($rolTexto, $palabraClave)) {
                    $esGerenciaOSupervision = true;
                    break;
                }
            }
        }

        $query = Venta::query()
            ->when($sucursalId > 0, fn ($q) => $q->where('sucursal_id', $sucursalId))
            ->where('estado', 'completada');

        if ($esGerenciaOSupervision) {
            // Supervisores, subgerentes, gerentes y administradores ven todos los tickets del día
            $query->whereDate('created_at', today());
        } else {
            // Empleados / cajeros normales ven únicamente los tickets de su turno abierto actual
            if ($cajaTurnoId <= 0) {
                $cajaTurnoId = (int) (CajaTurno::query()
                    ->when($sucursalId > 0, fn ($q) => $q->where('sucursal_id', $sucursalId))
                    ->where('user_id', $user->id)
                    ->where('estado', 'abierto')
                    ->latest('id')
                    ->value('id') ?? 0);
            }

            if ($cajaTurnoId > 0) {
                $query->where('caja_turno_id', $cajaTurnoId);
            } else {
                // Si el empleado no tiene turno abierto, no devuelve tickets de otros turnos
                return response()->json([]);
            }
        }

        $ventas = $query
            ->latest('id')
            ->take(50)
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
     * Asigna los datos fiscales de un cliente a una venta específica para permitir su timbrado.
     */
    public function asignarClienteVenta(Request $request, Venta $venta): JsonResponse
    {
        // Validar nombre y RFC fiscal distinto a genéricos
        $validated = $request->validate([
            'cliente_nombre' => ['required', 'string', 'max:150'],
            'cliente_rfc' => [
                'required',
                'string',
                'min:12',
                'max:13',
                'regex:/^[A-Z&Ñ]{3,4}[0-9]{6}[A-Z0-9]{3}$/i',
                'not_in:XAXX010101000,XEXX010101000,xxxxxxxxxxxx,xxxxxxxxxxxxx',
            ],
            'cliente_email' => ['nullable', 'email', 'max:150'],
        ]);

        $rfcNormalizado = strtoupper(trim($validated['cliente_rfc']));
        $nombreNormalizado = trim($validated['cliente_nombre']);

        // Buscar cliente en catálogo para obtener sus datos fiscales completos
        $clienteCatalogo = Cliente::query()
            ->whereRaw('UPPER(TRIM(rfc)) = ?', [$rfcNormalizado])
            ->first();

        $datosUpdate = [
            'cliente_nombre' => $nombreNormalizado,
            'cliente_rfc' => $rfcNormalizado,
        ];

        // Copiar campos fiscales adicionales a la venta si existen en la tabla ventas
        if (\Illuminate\Support\Facades\Schema::hasColumn('ventas', 'cliente_id') && $clienteCatalogo) {
            $datosUpdate['cliente_id'] = $clienteCatalogo->id;
        }
        if (\Illuminate\Support\Facades\Schema::hasColumn('ventas', 'cliente_email')) {
            $datosUpdate['cliente_email'] = $validated['cliente_email'] ?? ($clienteCatalogo->email ?? null);
        }
        if (\Illuminate\Support\Facades\Schema::hasColumn('ventas', 'cliente_codigo_postal') && ! empty($clienteCatalogo?->codigo_postal)) {
            $datosUpdate['cliente_codigo_postal'] = $clienteCatalogo->codigo_postal;
        }
        if (\Illuminate\Support\Facades\Schema::hasColumn('ventas', 'cliente_regimen_fiscal') && ! empty($clienteCatalogo?->regimen_fiscal)) {
            $datosUpdate['cliente_regimen_fiscal'] = $clienteCatalogo->regimen_fiscal;
        }
        if (\Illuminate\Support\Facades\Schema::hasColumn('ventas', 'uso_cfdi_sat') && ! empty($clienteCatalogo?->uso_cfdi)) {
            $datosUpdate['uso_cfdi_sat'] = $clienteCatalogo->uso_cfdi;
        }

        $venta->update($datosUpdate);

        return response()->json([
            'message' => 'Cliente fiscal asignado correctamente a la venta.',
            'venta' => Venta::query()->find($venta->id),
        ]);
    }

    /**
     * Elimina un cliente únicamente si no tiene facturas vinculadas.
     */
    public function eliminarClienteLealtad(Cliente $cliente): JsonResponse
    {
        // Verificar si el cliente tiene alguna venta facturada vinculada (por cliente_id o por RFC)
        $tieneFactura = $this->clienteTieneFacturaVinculada($cliente);

        if ($tieneFactura) {
            return response()->json([
                'message' => 'No se puede eliminar el cliente porque ya tiene una factura vinculada. Puedes homologarlo con otro cliente.',
            ], 422);
        }

        try {
            // Eliminar registro de cliente sin facturas vinculadas
            $cliente->delete();

            return response()->json([
                'message' => 'Cliente eliminado correctamente.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'No se pudo eliminar el cliente por registros relacionados en el sistema.',
            ], 422);
        }
    }

    /**
     * Homologa (fusiona) un cliente origen duplicado o inactivo hacia un cliente destino,
     * reasignando sus ventas y eliminando el registro origen si no tiene facturas vinculadas.
     */
    public function homologarClienteLealtad(Request $request, Cliente $cliente): JsonResponse
    {
        // Validar que el cliente destino exista en la tabla de clientes
        $validated = $request->validate([
            'cliente_destino_id' => [
                'required',
                'integer',
                'exists:clientes,id',
            ],
        ]);

        $destinoId = (int) $validated['cliente_destino_id'];

        // Validar explícitamente que el destino sea distinto al cliente origen
        if ($destinoId === (int) $cliente->id) {
            return response()->json([
                'message' => 'Debes seleccionar un cliente destino diferente al cliente a homologar.',
            ], 422);
        }

        /** @var Cliente $clienteDestino */
        $clienteDestino = Cliente::query()->findOrFail($destinoId);

        // Impedir la eliminación/homologación si el cliente origen tiene factura vinculada
        if ($this->clienteTieneFacturaVinculada($cliente)) {
            return response()->json([
                'message' => 'No se puede homologar/eliminar este cliente porque ya tiene una factura vinculada.',
            ], 422);
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($cliente, $clienteDestino): JsonResponse {
            // Reasignar ventas vinculadas por cliente_id hacia el cliente destino
            if (\Illuminate\Support\Facades\Schema::hasColumn('ventas', 'cliente_id')) {
                Venta::query()
                    ->where('cliente_id', $cliente->id)
                    ->update([
                        'cliente_id' => $clienteDestino->id,
                        'cliente_nombre' => $clienteDestino->nombre,
                        'cliente_rfc' => $clienteDestino->rfc ?: 'XAXX010101000',
                    ]);
            }

            // Reasignar ventas no facturadas asociadas al nombre o RFC del cliente origen
            $consultaVentas = Venta::query()
                ->where(function ($q): void {
                    $q->whereNull('estado_facturacion')
                        ->orWhere('estado_facturacion', '!=', 'facturada');
                });

            if (! empty($cliente->rfc)) {
                $consultaVentas->where(function ($q) use ($cliente): void {
                    $q->where('cliente_rfc', strtoupper(trim((string) $cliente->rfc)))
                        ->orWhere('cliente_nombre', $cliente->nombre);
                });
            } else {
                $consultaVentas->where('cliente_nombre', $cliente->nombre);
            }

            $consultaVentas->update([
                'cliente_nombre' => $clienteDestino->nombre,
                'cliente_rfc' => $clienteDestino->rfc ?: 'XAXX010101000',
            ]);

            // Eliminar el cliente duplicado o inactivo tras reasignar sus registros
            $cliente->delete();

            return response()->json([
                'message' => "Cliente \"{$cliente->nombre}\" homologado correctamente hacia \"{$clienteDestino->nombre}\".",
                'cliente_destino' => $clienteDestino,
            ]);
        });
    }

    /**
     * Determina si el cliente tiene al menos una venta facturada vinculada.
     */
    protected function clienteTieneFacturaVinculada(Cliente $cliente): bool
    {
        return Venta::query()
            ->where(function ($q) use ($cliente): void {
                if (\Illuminate\Support\Facades\Schema::hasColumn('ventas', 'cliente_id')) {
                    $q->where('cliente_id', $cliente->id);
                }
                if (! empty($cliente->rfc)) {
                    $method = \Illuminate\Support\Facades\Schema::hasColumn('ventas', 'cliente_id') ? 'orWhere' : 'where';
                    $q->{$method}(function ($sub) use ($cliente): void {
                        $sub->whereRaw('UPPER(TRIM(cliente_rfc)) = ?', [strtoupper(trim((string) $cliente->rfc))])
                            ->where('cliente_nombre', $cliente->nombre);
                    });
                }
            })
            ->where(function ($q): void {
                $q->where('estado_facturacion', 'facturada')
                    ->orWhereNotNull('uuid_fiscal');
            })
            ->exists();
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
    // SECCIÓN 5: COMANDAS ABIERTAS, PRECUENTA TÉRMICA Y ALERTAS DE PRECUENTA
    // =========================================================================

    /**
     * Devuelve las alertas recientes de precuenta enviadas desde el comandero.
     */
    public function alertasPrecuenta(Request $request): JsonResponse
    {
        $sucursalId = $request->integer('sucursal_id', 1);
        $alertas = cache()->get("pos_alertas_precuenta_{$sucursalId}", []);

        if (empty($alertas)) {
            $alertas = cache()->get('pos_alertas_precuenta_1', []);
        }
        if (empty($alertas)) {
            $alertas = cache()->get('pos_alertas_precuenta_0', []);
        }

        return response()->json(array_values($alertas));
    }

    /**
     * Lista todas las mesas con comandas activas y saldos pendientes.
     */
    public function comandasAbiertasRestaurante(Request $request): JsonResponse
    {
        $empresa = Empresa::query()->where('id', 3)->first()
            ?? Empresa::query()->where('giro_comercial', 'restaurantes_alimentos')->first();

        $empresaId = $empresa !== null ? (int) $empresa->id : 3;

        $comandas = Comanda::query()
            ->where(function ($q) use ($empresaId) {
                $q->where('empresa_id', $empresaId)->orWhereNull('empresa_id');
            })
            ->whereNull('venta_id')
            ->where('estado', '!=', 'cancelada')
            ->with(['items.producto', 'mesero:id,name'])
            ->orderBy('id', 'asc')
            ->get();

        $mesas = [];
        foreach ($comandas as $cmd) {
            $nombreMesa = trim((string) $cmd->mesa_o_referencia);
            if ($nombreMesa === '') {
                continue;
            }

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

                $precio = (float) ($item->producto?->precio_base ?? 0);
                $cant = (float) $item->cantidad;
                $sub = $precio * $cant;
                $mesas[$nombreMesa]['total'] += $sub;

                $mesas[$nombreMesa]['items'][] = [
                    'id' => $item->id,
                    'producto_id' => $item->producto_id,
                    'sku' => $item->producto?->sku ?? 'CMD',
                    'nombre' => $item->producto?->nombre ?? 'Platillo',
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

        $mesasPendientes = array_filter(array_values($mesas), fn ($m) => count($m['items']) > 0);

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
    // SECCIÓN 6: CORTES DE CAJA (CORTE X PARCIAL, RETIROS Y CORTE Z FINAL)
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
