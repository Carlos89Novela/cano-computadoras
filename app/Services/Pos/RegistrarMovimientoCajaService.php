<?php

namespace App\Services\Pos;

use App\Models\CajaMovimiento;
use App\Models\CajaTurno;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegistrarMovimientoCajaService
{
    /**
     * @param array{
     *     caja_turno_id: int,
     *     tipo: 'ingreso'|'retiro',
     *     concepto: string,
     *     monto: float|int|numeric-string,
     *     motivo: string,
     *     comprobante_numero?: string|null
     * } $datos
     */
    public function ejecutar(array $datos, User $actor, ?User $autorizador = null): CajaMovimiento
    {
        return DB::transaction(function () use ($datos, $actor, $autorizador): CajaMovimiento {
            /** @var CajaTurno $turno */
            $turno = CajaTurno::query()
                ->lockForUpdate()
                ->findOrFail($datos['caja_turno_id']);

            if ($turno->estado !== 'abierto') {
                throw ValidationException::withMessages([
                    'caja_turno_id' => 'No se pueden registrar movimientos en un turno de caja cerrado.',
                ]);
            }

            $monto = (float) $datos['monto'];
            $tipo = $datos['tipo'];

            if ($tipo === 'retiro') {
                $saldoEfectivoDisponible = (float) $turno->monto_esperado_efectivo;
                if ($monto > $saldoEfectivoDisponible) {
                    throw ValidationException::withMessages([
                        'monto' => 'El retiro ($'.number_format($monto, 2).') supera el efectivo disponible en caja ($'.number_format($saldoEfectivoDisponible, 2).').',
                    ]);
                }

                $nuevoTotalRetiros = (float) $turno->total_retiros + $monto;
                $nuevoMontoEsperado = (float) $turno->monto_esperado_efectivo - $monto;

                $turno->update([
                    'total_retiros' => $nuevoTotalRetiros,
                    'monto_esperado_efectivo' => $nuevoMontoEsperado,
                ]);
            } else {
                $nuevoTotalIngresos = (float) $turno->total_otros_ingresos + $monto;
                $nuevoMontoEsperado = (float) $turno->monto_esperado_efectivo + $monto;

                $turno->update([
                    'total_otros_ingresos' => $nuevoTotalIngresos,
                    'monto_esperado_efectivo' => $nuevoMontoEsperado,
                ]);
            }

            return CajaMovimiento::query()->create([
                'empresa_id' => $turno->empresa_id,
                'sucursal_id' => $turno->sucursal_id,
                'caja_turno_id' => $turno->id,
                'user_id' => $actor->id,
                'autorizado_por_id' => $autorizador !== null ? $autorizador->id : $actor->id,
                'tipo' => $tipo,
                'concepto' => $datos['concepto'],
                'monto' => $monto,
                'motivo' => $datos['motivo'],
                'comprobante_numero' => $datos['comprobante_numero'] ?? null,
                'realizado_at' => now(),
            ]);
        }, 3);
    }
}
