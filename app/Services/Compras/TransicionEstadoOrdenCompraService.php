<?php

namespace App\Services\Compras;

use App\Models\OrdenCompra;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TransicionEstadoOrdenCompraService
{
    public function solicitar(OrdenCompra $orden): OrdenCompra
    {
        if ($orden->estado !== 'borrador') {
            throw new InvalidArgumentException("Solo se pueden solicitar órdenes en estado 'borrador'.");
        }

        $orden->update([
            'estado' => 'solicitada',
        ]);

        return $orden;
    }

    public function autorizar(OrdenCompra $orden, User $usuario): OrdenCompra
    {
        if (! in_array($orden->estado, ['borrador', 'solicitada'], true)) {
            throw new InvalidArgumentException("La orden no se encuentra en un estado autorizable ('borrador' o 'solicitada').");
        }

        return DB::transaction(function () use ($orden, $usuario) {
            $orden->update([
                'estado' => 'autorizada',
                'autorizado_por_id' => $usuario->id,
                'autorizada_at' => Carbon::now(),
            ]);

            return $orden;
        });
    }

    public function cancelar(OrdenCompra $orden, string $motivo): OrdenCompra
    {
        if (in_array($orden->estado, ['recibida_parcial', 'recibida_total', 'cancelada'], true)) {
            throw new InvalidArgumentException('No se puede cancelar una orden con recepciones asentadas o ya cancelada.');
        }

        if (trim($motivo) === '') {
            throw new InvalidArgumentException('Debe proporcionar un motivo de cancelación.');
        }

        $orden->update([
            'estado' => 'cancelada',
            'motivo_cancelacion' => $motivo,
        ]);

        return $orden;
    }
}
