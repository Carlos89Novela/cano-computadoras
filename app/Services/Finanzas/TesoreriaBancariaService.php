<?php

namespace App\Services\Finanzas;

use App\Models\CuentaBancaria;
use App\Models\MovimientoBancario;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TesoreriaBancariaService
{
    /**
     * Registra un ingreso bancario y actualiza el saldo de la cuenta.
     */
    public function registrarIngreso(
        CuentaBancaria $cuenta,
        float $monto,
        string $concepto,
        User $usuario,
        ?string $fecha = null,
        ?string $referencia = null,
        ?Model $origen = null
    ): MovimientoBancario {
        if ($monto <= 0) {
            throw new InvalidArgumentException('El monto del ingreso bancario debe ser mayor a cero.');
        }

        return DB::transaction(function () use ($cuenta, $monto, $concepto, $usuario, $fecha, $referencia, $origen) {
            /** @var CuentaBancaria $cuentaBloqueada */
            $cuentaBloqueada = CuentaBancaria::query()->where('id', $cuenta->id)->lockForUpdate()->firstOrFail();

            $saldoAnterior = (float) $cuentaBloqueada->saldo_actual;
            $saldoPosterior = round($saldoAnterior + $monto, 2);

            $cuentaBloqueada->update([
                'saldo_actual' => $saldoPosterior,
            ]);

            /** @var MovimientoBancario $movimiento */
            $movimiento = MovimientoBancario::query()->create([
                'empresa_id' => $cuentaBloqueada->empresa_id,
                'cuenta_bancaria_id' => $cuentaBloqueada->id,
                'user_id' => $usuario->id,
                'tipo' => 'ingreso',
                'monto' => $monto,
                'saldo_anterior' => $saldoAnterior,
                'saldo_posterior' => $saldoPosterior,
                'fecha_operacion' => $fecha ?? Carbon::now()->toDateString(),
                'referencia' => $referencia,
                'concepto' => $concepto,
                'origen_type' => $origen ? get_class($origen) : null,
                'origen_id' => $origen ? (int) $origen->getKey() : null,
                'conciliado' => false,
            ]);

            return $movimiento;
        });
    }

    /**
     * Registra un egreso bancario descontando el saldo de la cuenta.
     */
    public function registrarEgreso(
        CuentaBancaria $cuenta,
        float $monto,
        string $concepto,
        User $usuario,
        ?string $fecha = null,
        ?string $referencia = null,
        ?Model $origen = null,
        bool $permitirSobregiro = false
    ): MovimientoBancario {
        if ($monto <= 0) {
            throw new InvalidArgumentException('El monto del egreso bancario debe ser mayor a cero.');
        }

        return DB::transaction(function () use ($cuenta, $monto, $concepto, $usuario, $fecha, $referencia, $origen, $permitirSobregiro) {
            /** @var CuentaBancaria $cuentaBloqueada */
            $cuentaBloqueada = CuentaBancaria::query()->where('id', $cuenta->id)->lockForUpdate()->firstOrFail();

            $saldoAnterior = (float) $cuentaBloqueada->saldo_actual;

            if (! $permitirSobregiro && $saldoAnterior < $monto) {
                throw new InvalidArgumentException("Fondos insuficientes en la cuenta '{$cuentaBloqueada->nombre_cuenta}'. Saldo disponible: $saldoAnterior");
            }

            $saldoPosterior = round($saldoAnterior - $monto, 2);

            $cuentaBloqueada->update([
                'saldo_actual' => $saldoPosterior,
            ]);

            /** @var MovimientoBancario $movimiento */
            $movimiento = MovimientoBancario::query()->create([
                'empresa_id' => $cuentaBloqueada->empresa_id,
                'cuenta_bancaria_id' => $cuentaBloqueada->id,
                'user_id' => $usuario->id,
                'tipo' => 'egreso',
                'monto' => $monto,
                'saldo_anterior' => $saldoAnterior,
                'saldo_posterior' => $saldoPosterior,
                'fecha_operacion' => $fecha ?? Carbon::now()->toDateString(),
                'referencia' => $referencia,
                'concepto' => $concepto,
                'origen_type' => $origen ? get_class($origen) : null,
                'origen_id' => $origen ? (int) $origen->getKey() : null,
                'conciliado' => false,
            ]);

            return $movimiento;
        });
    }

    /**
     * Ejecuta una transferencia atómica entre dos cuentas de la misma empresa.
     *
     * @return array{egreso: MovimientoBancario, ingreso: MovimientoBancario}
     */
    public function transferirEntreCuentas(
        CuentaBancaria $origen,
        CuentaBancaria $destino,
        float $monto,
        User $usuario,
        ?string $fecha = null,
        ?string $referencia = null
    ): array {
        if ($origen->id === $destino->id) {
            throw new InvalidArgumentException('La cuenta de origen y destino no pueden ser la misma.');
        }

        if ($origen->empresa_id !== $destino->empresa_id) {
            throw new InvalidArgumentException('Solo se permiten transferencias entre cuentas de la misma empresa.');
        }

        if ($monto <= 0) {
            throw new InvalidArgumentException('El monto a transferir debe ser mayor a cero.');
        }

        return DB::transaction(function () use ($origen, $destino, $monto, $usuario, $fecha, $referencia) {
            /** @var CuentaBancaria $origenBloqueada */
            $origenBloqueada = CuentaBancaria::query()->where('id', $origen->id)->lockForUpdate()->firstOrFail();
            /** @var CuentaBancaria $destinoBloqueada */
            $destinoBloqueada = CuentaBancaria::query()->where('id', $destino->id)->lockForUpdate()->firstOrFail();

            $saldoAnteriorOrigen = (float) $origenBloqueada->saldo_actual;
            if ($saldoAnteriorOrigen < $monto) {
                throw new InvalidArgumentException("Fondos insuficientes en la cuenta de origen: {$origenBloqueada->nombre_cuenta}");
            }

            $saldoPosteriorOrigen = round($saldoAnteriorOrigen - $monto, 2);
            $origenBloqueada->update(['saldo_actual' => $saldoPosteriorOrigen]);

            $saldoAnteriorDestino = (float) $destinoBloqueada->saldo_actual;
            $saldoPosteriorDestino = round($saldoAnteriorDestino + $monto, 2);
            $destinoBloqueada->update(['saldo_actual' => $saldoPosteriorDestino]);

            $fechaOp = $fecha ?? Carbon::now()->toDateString();

            /** @var MovimientoBancario $movEgreso */
            $movEgreso = MovimientoBancario::query()->create([
                'empresa_id' => $origenBloqueada->empresa_id,
                'cuenta_bancaria_id' => $origenBloqueada->id,
                'cuenta_destino_id' => $destinoBloqueada->id,
                'user_id' => $usuario->id,
                'tipo' => 'transferencia',
                'monto' => $monto,
                'saldo_anterior' => $saldoAnteriorOrigen,
                'saldo_posterior' => $saldoPosteriorOrigen,
                'fecha_operacion' => $fechaOp,
                'referencia' => $referencia,
                'concepto' => "Transferencia enviada a {$destinoBloqueada->nombre_cuenta} ({$destinoBloqueada->banco})",
                'conciliado' => false,
            ]);

            /** @var MovimientoBancario $movIngreso */
            $movIngreso = MovimientoBancario::query()->create([
                'empresa_id' => $destinoBloqueada->empresa_id,
                'cuenta_bancaria_id' => $destinoBloqueada->id,
                'cuenta_destino_id' => $origenBloqueada->id,
                'user_id' => $usuario->id,
                'tipo' => 'transferencia',
                'monto' => $monto,
                'saldo_anterior' => $saldoAnteriorDestino,
                'saldo_posterior' => $saldoPosteriorDestino,
                'fecha_operacion' => $fechaOp,
                'referencia' => $referencia,
                'concepto' => "Transferencia recibida desde {$origenBloqueada->nombre_cuenta} ({$origenBloqueada->banco})",
                'conciliado' => false,
            ]);

            return [
                'egreso' => $movEgreso,
                'ingreso' => $movIngreso,
            ];
        });
    }

    /**
     * Concilia un movimiento marcándolo como validado con el estado de cuenta.
     */
    public function conciliarMovimiento(MovimientoBancario $movimiento): MovimientoBancario
    {
        $movimiento->update([
            'conciliado' => true,
            'conciliado_at' => Carbon::now(),
        ]);

        return $movimiento;
    }
}
