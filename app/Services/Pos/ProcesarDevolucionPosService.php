<?php

namespace App\Services\Pos;

use App\Models\Almacen;
use App\Models\CajaTurno;
use App\Models\ExistenciaAlmacen;
use App\Models\MovimientoInventario;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaDetalle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ProcesarDevolucionPosService
{
    /**
     * @param array{
     *     venta_folio: string,
     *     motivo: string,
     *     detalles: array<int, array{detalle_id: int, cantidad: float|int|numeric-string}>
     * } $datos
     */
    public function ejecutar(array $datos, User $supervisor, Almacen $almacen, ?CajaTurno $turno): Venta
    {
        return DB::transaction(function () use ($datos, $supervisor, $almacen, $turno) {
            /** @var Venta $venta */
            $venta = Venta::query()
                ->where('folio', trim($datos['venta_folio']))
                ->with(['detalles.producto'])
                ->firstOrFail();

            $totalReembolsado = 0.0;

            foreach ($datos['detalles'] as $item) {
                $cantidadDevolver = (float) $item['cantidad'];
                if ($cantidadDevolver <= 0) {
                    continue;
                }

                /** @var VentaDetalle|null $detalle */
                $detalle = $venta->detalles->firstWhere('id', (int) $item['detalle_id']);
                if (! $detalle instanceof VentaDetalle) {
                    throw new InvalidArgumentException('La partida indicada no pertenece a la venta.');
                }

                if ($cantidadDevolver > (float) $detalle->cantidad) {
                    throw new InvalidArgumentException('No es posible devolver una cantidad mayor a la vendida.');
                }

                // 1. Reintegrar existencias al almacén
                /** @var ExistenciaAlmacen $existencia */
                $existencia = ExistenciaAlmacen::query()->firstOrCreate(
                    [
                        'empresa_id' => $almacen->empresa_id,
                        'almacen_id' => $almacen->id,
                        'producto_id' => $detalle->producto_id,
                    ],
                    [
                        'cantidad' => 0,
                        'cantidad_reservada' => 0,
                        'costo_promedio' => $detalle->costo_unitario ?? 0,
                    ]
                );

                $stockAnterior = (float) $existencia->cantidad;
                $stockNuevo = $stockAnterior + $cantidadDevolver;

                $existencia->update([
                    'cantidad' => $stockNuevo,
                ]);

                // 2. Registrar movimiento de entrada en Kardex
                MovimientoInventario::query()->create([
                    'empresa_id' => $almacen->empresa_id,
                    'almacen_id' => $almacen->id,
                    'producto_id' => $detalle->producto_id,
                    'grupo_id' => (string) Str::uuid(),
                    'folio' => 'DEV-'.now()->format('YmdHisv').'-'.Str::upper(Str::random(6)),
                    'tipo' => MovimientoInventario::TIPO_ENTRADA,
                    'cantidad' => $cantidadDevolver,
                    'costo_unitario' => $detalle->costo_unitario ?? 0,
                    'costo_total' => round(($detalle->costo_unitario ?? 0) * $cantidadDevolver, 4),
                    'stock_anterior' => $stockAnterior,
                    'stock_nuevo' => $stockNuevo,
                    'referencia_id' => $venta->id,
                    'referencia_tipo' => Venta::class,
                    'motivo' => 'Devolución de venta: '.$datos['motivo'],
                    'usuario_id' => $supervisor->id,
                ]);

                $totalReembolsado += round((float) $detalle->precio_unitario * $cantidadDevolver, 2);
            }

            // 3. Ajustar efectivo del turno de caja si está abierto
            if ($turno && $turno->estado === 'abierto' && $totalReembolsado > 0) {
                $turno->update([
                    'monto_esperado_efectivo' => max(0, (float) $turno->monto_esperado_efectivo - $totalReembolsado),
                ]);
            }

            return $venta;
        });
    }
}
