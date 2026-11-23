<?php

namespace App\Services\Compras;

use App\Models\Empresa;
use App\Models\OrdenCompra;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Servicio de dominio para registrar Órdenes de Compra a Proveedores.
 *
 * Calcula cada partida considerando que el `costo_unitario` capturado
 * ya incluye IVA (IVA incluido), desglosando subtotal e IVA hacia atrás
 * para mantener coherencia en totales, PDF, recepción de inventario y CXP.
 */
class CrearOrdenCompraService
{
    /**
     * Ejecuta el registro transaccional de una orden de compra y sus partidas.
     *
     * @param array{
     *     empresa_id: int,
     *     sucursal_id: int,
     *     almacen_id: int,
     *     proveedor_id: int,
     *     user_id: int,
     *     fecha_emision: string,
     *     fecha_entrega_esperada?: string|null,
     *     moneda?: string,
     *     tipo_cambio?: float|numeric-string,
     *     notas?: string|null,
     *     detalles: array<int, array{
     *         producto_id: int,
     *         cantidad: float|numeric-string,
     *         costo_unitario: float|numeric-string,
     *         descuento_unitario?: float|numeric-string,
     *         tasa_iva?: float|numeric-string
     *     }>
     * } $datos
     */
    public function ejecutar(array $datos): OrdenCompra
    {
        return DB::transaction(function () use ($datos) {
            /** @var Empresa $empresa */
            $empresa = Empresa::query()->findOrFail($datos['empresa_id']);
            /** @var Sucursal $sucursal */
            $sucursal = Sucursal::query()
                ->where('empresa_id', $empresa->id)
                ->findOrFail($datos['sucursal_id']);
            /** @var User $usuario */
            $usuario = User::query()->findOrFail($datos['user_id']);

            if (empty($datos['detalles'])) {
                throw new InvalidArgumentException('La orden de compra debe incluir al menos una partida.');
            }

            $folio = $this->generarFolio($sucursal);

            $subtotalAcumulado = 0.0;
            $descuentoAcumulado = 0.0;
            $ivaAcumulado = 0.0;
            $totalAcumulado = 0.0;

            $detallesParaCrear = [];

            foreach ($datos['detalles'] as $linea) {
                /** @var Producto $producto */
                $producto = Producto::query()
                    ->where('empresa_id', $empresa->id)
                    ->findOrFail($linea['producto_id']);

                $cantidad = (float) $linea['cantidad'];
                // El costo_unitario recibido ya viene con IVA incluido
                $costoUnitarioConIva = (float) $linea['costo_unitario'];
                $descuentoUnitarioConIva = (float) ($linea['descuento_unitario'] ?? 0.0);
                $tasaIva = isset($linea['tasa_iva']) ? (float) $linea['tasa_iva'] : 0.1600;
                $factorIva = 1.0 + max(0.0, $tasaIva);

                // Costo efectivo unitario con IVA y total de la partida (IVA incluido)
                $costoEfectivoConIva = max(0.0, $costoUnitarioConIva - $descuentoUnitarioConIva);
                $totalLinea = round($costoEfectivoConIva * $cantidad, 2);

                // Desglose de subtotal (sin IVA) e IVA a partir del importe con IVA incluido
                $subtotalLinea = round($totalLinea / $factorIva, 2);
                $ivaLinea = round($totalLinea - $subtotalLinea, 2);
                $ivaUnitario = $cantidad > 0 ? round($ivaLinea / $cantidad, 4) : 0.0;

                $subtotalAcumulado += $subtotalLinea;
                $descuentoAcumulado += round(($descuentoUnitarioConIva / $factorIva) * $cantidad, 2);
                $ivaAcumulado += $ivaLinea;
                $totalAcumulado += $totalLinea;

                $detallesParaCrear[] = [
                    'uuid' => (string) Str::uuid(),
                    'producto_id' => $producto->id,
                    'cantidad' => $cantidad,
                    'cantidad_recibida' => 0.0,
                    'costo_unitario' => $costoUnitarioConIva,
                    'descuento_unitario' => $descuentoUnitarioConIva,
                    'tasa_iva' => $tasaIva,
                    'monto_iva_unitario' => $ivaUnitario,
                    'subtotal' => $subtotalLinea,
                    'total' => $totalLinea,
                ];
            }

            /** @var OrdenCompra $orden */
            $orden = OrdenCompra::query()->create([
                'uuid' => (string) Str::uuid(),
                'empresa_id' => $empresa->id,
                'sucursal_id' => $sucursal->id,
                'almacen_id' => $datos['almacen_id'],
                'proveedor_id' => $datos['proveedor_id'],
                'user_id' => $usuario->id,
                'folio' => $folio,
                'estado' => 'borrador',
                'fecha_emision' => $datos['fecha_emision'],
                'fecha_entrega_esperada' => $datos['fecha_entrega_esperada'] ?? null,
                'subtotal' => round($subtotalAcumulado, 2),
                'descuento_total' => round($descuentoAcumulado, 2),
                'iva_total' => round($ivaAcumulado, 2),
                'total' => round($totalAcumulado, 2),
                'moneda' => $datos['moneda'] ?? 'MXN',
                'tipo_cambio' => (float) ($datos['tipo_cambio'] ?? 1.0000),
                'notas' => $datos['notas'] ?? null,
            ]);

            foreach ($detallesParaCrear as $detalle) {
                $orden->detalles()->create($detalle);
            }

            return $orden->load(['detalles.producto', 'proveedor', 'sucursal', 'almacen']);
        });
    }

    /**
     * Genera un folio consecutivo diario por sucursal (ej. OC-REST-01-20261123-0001).
     */
    protected function generarFolio(Sucursal $sucursal): string
    {
        $fecha = Carbon::now()->format('Ymd');
        $codigoSucursal = strtoupper((string) ($sucursal->codigo ?? 'SUC'));
        $prefijo = "OC-{$codigoSucursal}-{$fecha}";

        $ultimoFolio = OrdenCompra::query()
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
