<?php

namespace App\Services\Restaurante;

use App\Models\Almacen;
use App\Models\CajaTurno;
use App\Models\Comanda;
use App\Models\ComandaItem;
use App\Models\RestauranteMesa;
use App\Models\User;
use App\Models\Venta;
use App\Services\Pos\RegistrarVentaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class DividirCuentaService
{
    public function __construct(
        protected RegistrarVentaService $ventaService
    ) {}

    /**
     * @param array{
     *     forma_pago_sat: string,
     *     monto_recibido?: numeric|null,
     *     propina?: numeric|null,
     *     modo: 'items'|'monto',
     *     monto_fraccion?: numeric|null,
     *     items?: array<int, array{
     *         comanda_item_id: int,
     *         cantidad: numeric
     *     }>
     * } $datos
     * @return array{
     *     venta: Venta,
     *     comanda_cerrada: bool,
     *     token_autofactura: string
     * }
     */
    public function procesarPagoFraccion(RestauranteMesa $mesa, array $datos, User $cajero): array
    {
        return DB::transaction(function () use ($mesa, $datos, $cajero): array {
            /** @var Comanda|null $comanda */
            $comanda = $mesa->comandaActual;
            if ($comanda === null) {
                throw new InvalidArgumentException('La mesa no cuenta con una comanda activa.');
            }

            $area = $mesa->area;
            $almacen = Almacen::query()
                ->where('sucursal_id', $area->sucursal_id)
                ->where('activo', true)
                ->first();
            $almacenId = $almacen !== null ? (int) $almacen->id : 1;

            $turno = CajaTurno::query()
                ->where('sucursal_id', $area->sucursal_id)
                ->where('estado', 'abierto')
                ->latest('id')
                ->first();

            $lineasVenta = [];
            $totalCobro = 0.0;
            $comandaFinalizada = false;

            if ($datos['modo'] === 'items') {
                if (empty($datos['items'])) {
                    throw new InvalidArgumentException('Debe especificar al menos un producto a liquidar.');
                }

                foreach ($datos['items'] as $itemPago) {
                    /** @var ComandaItem $cItem */
                    $cItem = ComandaItem::query()
                        ->where('comanda_id', $comanda->id)
                        ->with('producto')
                        ->lockForUpdate()
                        ->findOrFail($itemPago['comanda_item_id']);

                    $cantCobrar = (float) $itemPago['cantidad'];
                    $cantRestante = (float) $cItem->cantidad;

                    if ($cantCobrar > $cantRestante) {
                        throw new InvalidArgumentException("La cantidad a cobrar ({$cantCobrar}) supera la cantidad pendiente ({$cantRestante}) para {$cItem->producto->nombre}.");
                    }

                    $precio = (float) $cItem->producto->precio_base;
                    $lineasVenta[] = [
                        'producto_id' => $cItem->producto_id,
                        'cantidad' => $cantCobrar,
                        'precio_unitario' => $precio,
                        'tasa_iva' => 0.1600,
                    ];
                    $totalCobro += ($cantCobrar * $precio);

                    // Reducir o eliminar el item de la comanda
                    if ($cantCobrar >= $cantRestante) {
                        $cItem->delete();
                    } else {
                        $cItem->update(['cantidad' => $cantRestante - $cantCobrar]);
                    }
                }

                // Verificar si quedan items pendientes en la comanda
                $itemsRestantes = ComandaItem::query()->where('comanda_id', $comanda->id)->count();
                $comandaFinalizada = ($itemsRestantes === 0);

            } else {
                // Modo monto / partes iguales
                $monto = (float) ($datos['monto_fraccion'] ?? 0);
                if ($monto <= 0) {
                    throw new InvalidArgumentException('El monto a liquidar debe ser mayor a cero.');
                }

                $totalCobro = $monto;

                // Para liquidación por monto, se genera una línea de servicio consolidada
                $primerItem = $comanda->items()->with('producto')->first();
                $prodId = $primerItem !== null ? $primerItem->producto_id : 1;

                $lineasVenta[] = [
                    'producto_id' => $prodId,
                    'cantidad' => 1.0,
                    'precio_unitario' => $monto,
                    'tasa_iva' => 0.1600,
                ];

                // Reducir acumulado en la comanda
                $nuevoTotal = max(0.0, ((float) $comanda->total_acumulado) - $monto);
                $comanda->update(['total_acumulado' => $nuevoTotal]);
                $comandaFinalizada = ($nuevoTotal <= 0.01);
            }

            $tokenAutofactura = Str::lower(Str::random(32));

            $venta = $this->ventaService->ejecutar([
                'empresa_id' => $area->empresa_id,
                'sucursal_id' => $area->sucursal_id,
                'almacen_id' => $almacenId,
                'caja_turno_id' => $turno?->id,
                'user_id' => $cajero->id,
                'cliente_nombre' => "{$mesa->numero_o_nombre} (Fracción)",
                'cliente_rfc' => 'XAXX010101000',
                'forma_pago_sat' => $datos['forma_pago_sat'],
                'lineas' => $lineasVenta,
                'pagos' => [
                    [
                        'forma_pago_sat' => $datos['forma_pago_sat'],
                        'monto' => $totalCobro,
                        'monto_recibido' => $datos['monto_recibido'] ?? $totalCobro,
                        'cambio_devuelto' => max(0.0, ((float) ($datos['monto_recibido'] ?? $totalCobro)) - $totalCobro),
                    ],
                ],
            ]);

            $venta->update([
                'token_autofactura' => $tokenAutofactura,
                'autofactura_limite_at' => now()->addDays(3),
            ]);

            if ($comandaFinalizada) {
                $comanda->update([
                    'estado' => 'completada',
                    'venta_id' => $venta->id,
                    'finalizada_at' => now(),
                ]);

                $mesa->update([
                    'estado' => 'disponible',
                    'comanda_actual_id' => null,
                ]);
            }

            return [
                'venta' => $venta,
                'comanda_cerrada' => $comandaFinalizada,
                'token_autofactura' => $tokenAutofactura,
            ];
        });
    }
}
