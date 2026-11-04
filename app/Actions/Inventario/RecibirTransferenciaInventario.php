<?php

namespace App\Actions\Inventario;

use App\Models\Almacen;
use App\Models\ExistenciaAlmacen;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\TransferenciaInventario;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RecibirTransferenciaInventario
{
    /**
     * @param  array{
     *     observaciones?: string|null,
     *     lineas?: list<array{
     *         detalle_id: int,
     *         cantidad_recibida: string
     *     }>
     * }  $datos
     */
    public function ejecutar(
        TransferenciaInventario $transferencia,
        User $actor,
        array $datos = []
    ): TransferenciaInventario {
        if (! $actor->can('inventario.transferir') && ! $actor->hasRole('administrador')) {
            throw new AuthorizationException('No tienes permiso para recibir transferencias.');
        }

        if ($transferencia->estado !== TransferenciaInventario::ESTADO_EN_TRANSITO) {
            throw ValidationException::withMessages([
                'transferencia' => 'La transferencia no se encuentra en tránsito o ya fue completada.',
            ]);
        }

        return DB::transaction(function () use ($transferencia, $actor, $datos): TransferenciaInventario {
            /** @var TransferenciaInventario $transfBloqueada */
            $transfBloqueada = TransferenciaInventario::query()->lockForUpdate()->findOrFail($transferencia->id);

            /** @var Almacen $destino */
            $destino = Almacen::query()->lockForUpdate()->findOrFail($transfBloqueada->almacen_destino_id);

            /** @var Almacen $transito */
            $transito = Almacen::query()->lockForUpdate()->findOrFail((int) $transfBloqueada->almacen_transito_id);

            $ocurridoAt = now();
            $grupoId = $transfBloqueada->grupo_id;

            $detalles = $transfBloqueada->detalles()->lockForUpdate()->get();
            $productoIds = $detalles->pluck('producto_id')->all();
            sort($productoIds);
            Producto::query()->whereIn('id', $productoIds)->lockForUpdate()->get();

            $recepcionPorDetalle = [];
            if (! empty($datos['lineas'])) {
                foreach ($datos['lineas'] as $l) {
                    $recepcionPorDetalle[(int) $l['detalle_id']] = (string) $l['cantidad_recibida'];
                }
            }

            foreach ($detalles as $detalle) {
                $cantRecibida = isset($recepcionPorDetalle[$detalle->id])
                    ? $recepcionPorDetalle[$detalle->id]
                    : (string) $detalle->cantidad_despachada;

                $productoId = $detalle->producto_id;
                $costoUnitario = (string) $detalle->costo_unitario;

                $detalle->update([
                    'cantidad_recibida' => $cantRecibida,
                ]);

                // 1. Descargar del almacén virtual de tránsito
                /** @var ExistenciaAlmacen|null $existenciaTransito */
                $existenciaTransito = ExistenciaAlmacen::query()
                    ->where('empresa_id', $transfBloqueada->empresa_id)
                    ->where('almacen_id', $transito->id)
                    ->where('producto_id', $productoId)
                    ->lockForUpdate()
                    ->first();

                $cantTransitoAnterior = $existenciaTransito !== null ? (string) $existenciaTransito->cantidad : '0.0000';
                $cantTransitoNueva = bcsub($cantTransitoAnterior, (string) $detalle->cantidad_despachada, 4);

                if ($existenciaTransito !== null) {
                    $existenciaTransito->update([
                        'cantidad' => $cantTransitoNueva,
                        'actualizado_por_id' => $actor->id,
                    ]);
                }

                // 2. Ingresar a Destino y calcular costo promedio ponderado
                /** @var ExistenciaAlmacen|null $existenciaDestino */
                $existenciaDestino = ExistenciaAlmacen::query()
                    ->where('empresa_id', $transfBloqueada->empresa_id)
                    ->where('almacen_id', $destino->id)
                    ->where('producto_id', $productoId)
                    ->lockForUpdate()
                    ->first();

                $cantDestinoAnterior = $existenciaDestino !== null ? (string) $existenciaDestino->cantidad : '0.0000';
                $costoDestinoAnterior = $existenciaDestino !== null ? (string) $existenciaDestino->costo_promedio : '0.0000';

                $cantDestinoNueva = bcadd($cantDestinoAnterior, $cantRecibida, 4);
                $valorDestinoAnterior = bcmul($cantDestinoAnterior, $costoDestinoAnterior, 8);
                $valorTransferido = bcmul($cantRecibida, $costoUnitario, 8);
                $valorDestinoTotal = bcadd($valorDestinoAnterior, $valorTransferido, 8);
                $costoDestinoNuevo = bccomp($cantDestinoNueva, '0.0000', 4) === 1
                    ? bcdiv($valorDestinoTotal, $cantDestinoNueva, 4)
                    : $costoDestinoAnterior;

                if ($existenciaDestino === null) {
                    ExistenciaAlmacen::query()->create([
                        'empresa_id' => $transfBloqueada->empresa_id,
                        'almacen_id' => $destino->id,
                        'producto_id' => $productoId,
                        'cantidad' => $cantDestinoNueva,
                        'cantidad_reservada' => '0.0000',
                        'costo_promedio' => $costoDestinoNuevo,
                        'actualizado_por_id' => $actor->id,
                    ]);
                } else {
                    $existenciaDestino->update([
                        'cantidad' => $cantDestinoNueva,
                        'costo_promedio' => $costoDestinoNuevo,
                        'actualizado_por_id' => $actor->id,
                    ]);
                }

                // Bitácora de entrada en destino
                MovimientoInventario::query()->create([
                    'empresa_id' => $transfBloqueada->empresa_id,
                    'almacen_id' => $destino->id,
                    'producto_id' => $productoId,
                    'grupo_id' => $grupoId,
                    'folio' => 'MOV-'.now()->format('YmdHisv').'-'.Str::upper(Str::random(6)),
                    'tipo' => MovimientoInventario::TIPO_TRANSFERENCIA_ENTRADA,
                    'cantidad' => $cantRecibida,
                    'existencia_anterior' => $cantDestinoAnterior,
                    'existencia_nueva' => $cantDestinoNueva,
                    'costo_unitario' => $costoUnitario,
                    'referencia_tipo' => TransferenciaInventario::class,
                    'referencia_id' => $transfBloqueada->id,
                    'motivo' => "Recepción en sucursal destino: {$transfBloqueada->motivo}",
                    'usuario_id' => $actor->id,
                    'ocurrido_at' => $ocurridoAt,
                ]);
            }

            $transfBloqueada->update([
                'estado' => TransferenciaInventario::ESTADO_COMPLETADA,
                'recibido_por_id' => $actor->id,
                'recibido_at' => $ocurridoAt,
                'observaciones_recepcion' => $datos['observaciones'] ?? null,
            ]);

            return $transfBloqueada->fresh()->load([
                'empresa',
                'almacenOrigen',
                'almacenDestino',
                'almacenTransito',
                'solicitadoPor',
                'despachadoPor',
                'recibidoPor',
                'detalles.producto',
            ]);
        }, 3);
    }
}
