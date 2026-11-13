<?php

namespace App\Services\Pos;

use App\Models\Almacen;
use App\Models\CajaTurno;
use App\Models\Empresa;
use App\Models\ExistenciaAlmacen;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaPago;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class RegistrarVentaService
{
    /**
     * Procesa una venta de mostrador (POS), descuenta inventario (directo o por insumos de receta) y asienta en Kardex.
     *
     * @param array{
     *     uuid?: string,
     *     empresa_id: int,
     *     sucursal_id: int,
     *     almacen_id: int,
     *     caja_turno_id?: int|null,
     *     user_id: int,
     *     cliente_id?: int|null,
     *     cliente_nombre?: string,
     *     cliente_rfc?: string,
     *     cliente_email?: string|null,
     *     cliente_telefono?: string|null,
     *     cliente_regimen_fiscal?: string|null,
     *     cliente_codigo_postal?: string|null,
     *     forma_pago_sat?: string,
     *     metodo_pago_sat?: string,
     *     uso_cfdi_sat?: string,
     *     origen_registro?: string,
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
     * } $datos
     */
    public function ejecutar(array $datos): Venta
    {
        return DB::transaction(function () use ($datos) {
            $uuidVenta = $datos['uuid'] ?? (string) Str::uuid();

            // 1. Idempotencia: evitar duplicados en sincronización offline
            $ventaExistente = Venta::query()->where('uuid', $uuidVenta)->first();
            if ($ventaExistente !== null) {
                return $ventaExistente;
            }

            // 2. Resolución tolerante de Empresa, Sucursal y Almacén
            $empresa = Empresa::query()->findOrFail($datos['empresa_id']);

            $sucursal = Sucursal::query()->where('empresa_id', $empresa->id)->find($datos['sucursal_id'])
                ?? Sucursal::query()->where('empresa_id', $empresa->id)->where('activo', true)->first()
                ?? Sucursal::query()->where('empresa_id', $empresa->id)->firstOrFail();

            $almacen = Almacen::query()
                ->where('empresa_id', $empresa->id)
                ->where('sucursal_id', $sucursal->id)
                ->find($datos['almacen_id'])
                ?? Almacen::query()
                    ->where('empresa_id', $empresa->id)
                    ->where('activo', true)
                    ->first()
                ?? Almacen::query()->findOrFail($datos['almacen_id']);

            /** @var User $cajero */
            $cajero = User::query()->findOrFail($datos['user_id']);

            /** @var CajaTurno|null $turno */
            $turno = null;
            if (! empty($datos['caja_turno_id'])) {
                $turno = CajaTurno::query()
                    ->where('empresa_id', $empresa->id)
                    ->where('estado', 'abierto')
                    ->find($datos['caja_turno_id']);
            }

            if (empty($datos['lineas'])) {
                throw new InvalidArgumentException('La venta debe contener al menos un producto.');
            }

            $folio = $this->generarFolio($sucursal);

            $subtotalSinImpuestos = 0.0;
            $descuentoTotal = 0.0;
            $ivaTotal = 0.0;
            $totalVenta = 0.0;
            $costoTotalInventario = 0.0;

            $detallesParaCrear = [];

            // 3. Procesamiento de Líneas de Venta
            foreach ($datos['lineas'] as $linea) {
                /** @var Producto $producto */
                $producto = Producto::query()
                    ->where('empresa_id', $empresa->id)
                    ->with(['receta.ingredientes.insumo'])
                    ->findOrFail($linea['producto_id']);

                $cantidad = (float) $linea['cantidad'];
                if ($cantidad <= 0) {
                    throw new InvalidArgumentException("La cantidad para el producto [{$producto->nombre}] debe ser mayor a 0.");
                }

                $origen = $datos['origen_registro'] ?? 'online';

                // Caso A: Platillo o bebida elaborado con receta (BOM)
                if ($producto->es_receta && $producto->receta !== null && $producto->receta->ingredientes->isNotEmpty()) {
                    $costoUnitarioPromedio = 0.0;

                    foreach ($producto->receta->ingredientes as $ingrediente) {
                        $factorMerma = bcadd('1', bcdiv((string) ($ingrediente->porcentaje_merma ?? 0), '100', 4), 4);
                        $consumoPorUnidad = bcmul((string) $ingrediente->cantidad_requerida, $factorMerma, 4);
                        $consumoTotal = (float) bcmul($consumoPorUnidad, (string) $cantidad, 4);

                        /** @var ExistenciaAlmacen|null $existenciaInsumo */
                        $existenciaInsumo = ExistenciaAlmacen::query()
                            ->where('almacen_id', $almacen->id)
                            ->where('producto_id', $ingrediente->insumo_producto_id)
                            ->lockForUpdate()
                            ->first();

                        $cantFisica = $existenciaInsumo !== null ? (float) $existenciaInsumo->cantidad : 0.0;
                        $cantReservada = $existenciaInsumo !== null ? (float) $existenciaInsumo->cantidad_reservada : 0.0;
                        $cantDisponible = max(0.0, $cantFisica - $cantReservada);

                        // Si hay stock configurado se valida; si no hay existencias iniciales cargadas, se permite para no frenar la venta
                        if ($origen === 'online' && $existenciaInsumo !== null && $cantDisponible < $consumoTotal) {
                            $nombreInsumo = $ingrediente->insumo->nombre ?? 'Insumo';
                            throw new RuntimeException("Stock insuficiente del insumo [{$nombreInsumo}] para preparar [{$producto->nombre}]. Disponible: {$cantDisponible}, Requerido: {$consumoTotal}.");
                        }

                        $costoInsumo = $existenciaInsumo !== null && (float) $existenciaInsumo->costo_promedio > 0
                            ? (float) $existenciaInsumo->costo_promedio
                            : (float) ($ingrediente->insumo->costo_referencia ?? 0.0);

                        $costoUnitarioPromedio += ((float) $consumoPorUnidad) * $costoInsumo;
                    }

                    $existencia = null;
                } else {
                    // Caso B: Insumo directo o producto regular
                    /** @var ExistenciaAlmacen|null $existencia */
                    $existencia = ExistenciaAlmacen::query()
                        ->where('almacen_id', $almacen->id)
                        ->where('producto_id', $producto->id)
                        ->lockForUpdate()
                        ->first();

                    $cantidadFisica = $existencia !== null ? (float) $existencia->cantidad : 0.0;
                    $cantidadReservada = $existencia !== null ? (float) $existencia->cantidad_reservada : 0.0;
                    $cantidadDisponible = max(0.0, $cantidadFisica - $cantidadReservada);

                    $costoUnitarioPromedio = $existencia !== null && (float) $existencia->costo_promedio > 0
                        ? (float) $existencia->costo_promedio
                        : (float) $producto->costo_referencia;

                    if ($origen === 'online' && $existencia !== null && $cantidadDisponible < $cantidad && ! $producto->es_receta) {
                        throw new RuntimeException("Stock insuficiente para el producto [{$producto->sku} - {$producto->nombre}]. Disponible: {$cantidadDisponible}, Solicitado: {$cantidad}.");
                    }
                }

                // Cálculo económico (precios con IVA incluido al comensal)
                $precioConIva = (float) $linea['precio_unitario'];
                $descuentoUnitario = (float) ($linea['descuento_unitario'] ?? 0.0);
                $tasaIva = isset($linea['tasa_iva']) ? (float) $linea['tasa_iva'] : 0.1600;

                $precioUnitarioNeto = round($precioConIva / (1.0 + $tasaIva), 4);
                $descuentoNeto = round($descuentoUnitario / (1.0 + $tasaIva), 4);
                $precioNetoCobrado = max(0.0, $precioUnitarioNeto - $descuentoNeto);

                $subtotalLinea = round($precioNetoCobrado * $cantidad, 2);
                $totalLinea = round(max(0.0, $precioConIva - $descuentoUnitario) * $cantidad, 2);
                $ivaLinea = round($totalLinea - $subtotalLinea, 2);
                $ivaUnitario = $cantidad > 0 ? round($ivaLinea / $cantidad, 4) : 0.0;

                $costoLinea = round($costoUnitarioPromedio * $cantidad, 4);

                $subtotalSinImpuestos += $subtotalLinea;
                $descuentoTotal += round($descuentoNeto * $cantidad, 2);
                $ivaTotal += $ivaLinea;
                $totalVenta += $totalLinea;
                $costoTotalInventario += $costoLinea;

                $detallesParaCrear[] = [
                    'uuid' => $linea['uuid'] ?? (string) Str::uuid(),
                    'producto_id' => $producto->id,
                    'producto_model' => $producto,
                    'almacen_id' => $almacen->id,
                    'codigo_barras' => $producto->codigo_barras,
                    'sku' => $producto->sku,
                    'descripcion_producto' => $producto->nombre,
                    'clave_sat_producto' => '01010101',
                    'clave_sat_unidad' => 'H87',
                    'cantidad' => $cantidad,
                    'costo_unitario_promedio' => $costoUnitarioPromedio,
                    'precio_unitario' => $precioUnitarioNeto,
                    'descuento_unitario' => $descuentoNeto,
                    'tasa_iva' => $tasaIva,
                    'monto_iva_unitario' => $ivaUnitario,
                    'subtotal' => $subtotalLinea,
                    'total' => $totalLinea,
                    'existencia_model' => $existencia,
                    'costo_unitario' => $costoUnitarioPromedio,
                ];
            }

            // 4. Encabezado de Venta
            // Generar token único para el portal público de autofacturación
            $tokenAutofactura = Str::lower(Str::random(32));

            /** @var Venta $venta */
            $venta = Venta::query()->create([
                'uuid' => $uuidVenta,
                'token_autofactura' => $tokenAutofactura,
                'autofactura_limite_at' => now()->addDays(3),
                'empresa_id' => $empresa->id,
                'sucursal_id' => $sucursal->id,
                'almacen_id' => $almacen->id,
                'caja_turno_id' => $turno?->id,
                'user_id' => $cajero->id,
                'cliente_id' => $datos['cliente_id'] ?? null,
                'folio' => $folio,
                'cliente_nombre' => $datos['cliente_nombre'] ?? 'Público General',
                'cliente_rfc' => strtoupper($datos['cliente_rfc'] ?? 'XAXX010101000'),
                'cliente_email' => $datos['cliente_email'] ?? null,
                'cliente_telefono' => $datos['cliente_telefono'] ?? null,
                'cliente_regimen_fiscal' => $datos['cliente_regimen_fiscal'] ?? null,
                'cliente_codigo_postal' => $datos['cliente_codigo_postal'] ?? null,
                'subtotal_sin_impuestos' => $subtotalSinImpuestos,
                'descuento_total' => $descuentoTotal,
                'base_imponible' => $subtotalSinImpuestos,
                'iva_total' => $ivaTotal,
                'total' => round($totalVenta, 2),
                'costo_total_inventario' => $costoTotalInventario,
                'forma_pago_sat' => $datos['forma_pago_sat'] ?? '01',
                'metodo_pago_sat' => $datos['metodo_pago_sat'] ?? 'PUE',
                'uso_cfdi_sat' => $datos['uso_cfdi_sat'] ?? 'S01',
                'estado' => 'completada',
                'origen_registro' => $datos['origen_registro'] ?? 'online',
                'sincronizado_at' => ($datos['origen_registro'] ?? 'online') === 'offline' ? now() : null,
                'estado_facturacion' => 'no_facturada',
                'notas' => $datos['notas'] ?? null,
            ]);

            // 5. Persistencia de Partidas, Descuento y Kardex
            foreach ($detallesParaCrear as $detalleItem) {
                /** @var Producto $prodModel */
                $prodModel = $detalleItem['producto_model'];
                unset($detalleItem['producto_model']);

                /** @var ExistenciaAlmacen|null $existencia */
                $existencia = $detalleItem['existencia_model'];
                unset($detalleItem['existencia_model']);

                $costoUnit = (float) $detalleItem['costo_unitario'];
                unset($detalleItem['costo_unitario']);

                $venta->detalles()->create($detalleItem);

                if ($prodModel->es_receta && $prodModel->receta !== null && $prodModel->receta->ingredientes->isNotEmpty()) {
                    foreach ($prodModel->receta->ingredientes as $ingrediente) {
                        $factorMerma = bcadd('1', bcdiv((string) ($ingrediente->porcentaje_merma ?? 0), '100', 4), 4);
                        $consumoPorUnidad = bcmul((string) $ingrediente->cantidad_requerida, $factorMerma, 4);
                        $consumoTotal = (float) bcmul($consumoPorUnidad, (string) $detalleItem['cantidad'], 4);

                        $this->descontarStockInsumo(
                            empresaId: $empresa->id,
                            almacenId: $almacen->id,
                            insumoId: $ingrediente->insumo_producto_id,
                            cantidad: $consumoTotal,
                            motivo: "Consumo por venta de {$detalleItem['cantidad']}x {$prodModel->nombre} (Folio: {$venta->folio})",
                            userId: $cajero->id,
                            ventaId: $venta->id
                        );
                    }
                } else {
                    $stockAnterior = $existencia !== null ? (float) $existencia->cantidad : 0.0;
                    $stockNuevo = max(0.0, $stockAnterior - (float) $detalleItem['cantidad']);

                    if ($existencia !== null) {
                        $existencia->cantidad = $stockNuevo;
                        $existencia->actualizado_por_id = $cajero->id;
                        $existencia->save();
                    } else {
                        ExistenciaAlmacen::query()->create([
                            'empresa_id' => $empresa->id,
                            'almacen_id' => $almacen->id,
                            'producto_id' => $detalleItem['producto_id'],
                            'cantidad' => 0.0,
                            'cantidad_reservada' => 0.0,
                            'costo_promedio' => $costoUnit,
                            'actualizado_por_id' => $cajero->id,
                        ]);
                    }

                    MovimientoInventario::query()->create([
                        'empresa_id' => $empresa->id,
                        'almacen_id' => $almacen->id,
                        'producto_id' => $detalleItem['producto_id'],
                        'grupo_id' => (string) Str::uuid(),
                        'folio' => 'MOV-'.now()->format('YmdHisv').'-'.Str::upper(Str::random(6)),
                        'tipo' => MovimientoInventario::TIPO_SALIDA,
                        'motivo' => 'Venta en mostrador POS',
                        'cantidad' => $detalleItem['cantidad'],
                        'costo_unitario' => $costoUnit,
                        'existencia_anterior' => $stockAnterior,
                        'existencia_nueva' => $stockNuevo,
                        'referencia_id' => $venta->id,
                        'referencia_tipo' => Venta::class,
                        'user_id' => $venta->user_id,
                        'ocurrido_at' => now(),
                    ]);
                }
            }

            // 6. Registro de Pagos e Incrementos de Caja
            foreach ($datos['pagos'] as $pagoItem) {
                $montoPago = (float) $pagoItem['monto'];
                $formaPago = $pagoItem['forma_pago_sat'];

                VentaPago::query()->create([
                    'uuid' => $pagoItem['uuid'] ?? (string) Str::uuid(),
                    'venta_id' => $venta->id,
                    'caja_turno_id' => $turno?->id,
                    'forma_pago_sat' => $formaPago,
                    'monto' => $montoPago,
                    'monto_recibido' => $pagoItem['monto_recibido'] ?? $montoPago,
                    'cambio_devuelto' => $pagoItem['cambio_devuelto'] ?? 0.0,
                    'referencia_bancaria' => $pagoItem['referencia_bancaria'] ?? null,
                ]);

                if ($turno !== null) {
                    match ($formaPago) {
                        '01' => $turno->increment('total_ventas_efectivo', $montoPago),
                        '04', '28' => $turno->increment('total_ventas_tarjeta', $montoPago),
                        '03' => $turno->increment('total_ventas_transferencia', $montoPago),
                        default => $turno->increment('total_otros_ingresos', $montoPago),
                    };
                }
            }

            if ($turno !== null) {
                $montoEsperado = (float) $turno->monto_inicial
                    + (float) $turno->total_ventas_efectivo
                    + (float) $turno->total_otros_ingresos
                    - (float) $turno->total_retiros;

                $turno->update(['monto_esperado_efectivo' => $montoEsperado]);
            }

            return $venta->load(['detalles', 'pagos', 'almacen', 'sucursal']);
        });
    }

    protected function descontarStockInsumo(
        int $empresaId,
        int $almacenId,
        int $insumoId,
        float $cantidad,
        string $motivo,
        int $userId,
        int $ventaId
    ): void {
        /** @var ExistenciaAlmacen $existencia */
        $existencia = ExistenciaAlmacen::query()
            ->lockForUpdate()
            ->firstOrCreate(
                [
                    'empresa_id' => $empresaId,
                    'almacen_id' => $almacenId,
                    'producto_id' => $insumoId,
                ],
                [
                    'cantidad' => 0.0000,
                    'cantidad_reservada' => 0.0000,
                    'costo_promedio' => 0.0000,
                    'actualizado_por_id' => $userId,
                ]
            );

        $anterior = (float) $existencia->cantidad;
        $nueva = max(0.0, $anterior - $cantidad);

        $existencia->update([
            'cantidad' => $nueva,
            'actualizado_por_id' => $userId,
        ]);

        MovimientoInventario::query()->create([
            'empresa_id' => $empresaId,
            'almacen_id' => $almacenId,
            'producto_id' => $insumoId,
            'user_id' => $userId,
            'grupo_id' => (string) Str::uuid(),
            'folio' => 'MOV-REC-'.now()->format('YmdHisv').'-'.Str::upper(Str::random(4)),
            'tipo' => MovimientoInventario::TIPO_SALIDA,
            'cantidad' => $cantidad,
            'existencia_anterior' => $anterior,
            'existencia_nueva' => $nueva,
            'costo_unitario' => (float) $existencia->costo_promedio,
            'motivo' => $motivo,
            'referencia_id' => $ventaId,
            'referencia_tipo' => Venta::class,
            'ocurrido_at' => now(),
        ]);
    }

    protected function generarFolio(Sucursal $sucursal): string
    {
        $fecha = Carbon::now()->format('Ymd');
        $codigoSucursal = strtoupper((string) ($sucursal->codigo ?? 'SUC'));
        $prefijo = "VTA-{$codigoSucursal}-{$fecha}";

        $ultimoFolio = Venta::query()
            ->where('sucursal_id', $sucursal->id)
            ->where('folio', 'like', "{$prefijo}-%")
            ->orderByDesc('id')
            ->value('folio');

        $consecutivo = 1;
        if ($ultimoFolio !== null) {
            $partes = explode('-', (string) $ultimoFolio);
            $consecutivo = ((int) end($partes)) + 1;
        }

        return sprintf('%s-%04d', $prefijo, $consecutivo);
    }
}