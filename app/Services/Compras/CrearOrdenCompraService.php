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

class CrearOrdenCompraService
{
    /**
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
                $costoUnitario = (float) $linea['costo_unitario'];
                $descuentoUnitario = (float) ($linea['descuento_unitario'] ?? 0.0);
                $tasaIva = isset($linea['tasa_iva']) ? (float) $linea['tasa_iva'] : 0.1600;

                $costoEfectivo = max(0.0, $costoUnitario - $descuentoUnitario);
                $subtotalLinea = round($costoEfectivo * $cantidad, 2);
                $ivaUnitario = round($costoEfectivo * $tasaIva, 4);
                $ivaLinea = round($ivaUnitario * $cantidad, 2);
                $totalLinea = round($subtotalLinea + $ivaLinea, 2);

                $subtotalAcumulado += $subtotalLinea;
                $descuentoAcumulado += round($descuentoUnitario * $cantidad, 2);
                $ivaAcumulado += $ivaLinea;
                $totalAcumulado += $totalLinea;

                $detallesParaCrear[] = [
                    'uuid' => (string) Str::uuid(),
                    'producto_id' => $producto->id,
                    'cantidad' => $cantidad,
                    'cantidad_recibida' => 0.0,
                    'costo_unitario' => $costoUnitario,
                    'descuento_unitario' => $descuentoUnitario,
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
                'subtotal' => $subtotalAcumulado,
                'descuento_total' => $descuentoAcumulado,
                'iva_total' => $ivaAcumulado,
                'total' => $totalAcumulado,
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
