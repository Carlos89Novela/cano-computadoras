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

class DespacharTransferenciaInventario
{
    /**
     * @param  array{
     *     observaciones?: string|null,
     *     lineas?: list<array{
     *         detalle_id: int,
     *         cantidad_despachada: string
     *     }>
     * }  $datos
     */
    public function ejecutar(
        TransferenciaInventario $transferencia,
        User $actor,
        array $datos = []
    ): TransferenciaInventario {
        if (! $actor->can('inventario.transferir') && ! $actor->hasRole('administrador')) {
            throw new AuthorizationException('No tienes permiso para despachar transferencias.');
        }

        if ($transferencia->estado !== TransferenciaInventario::ESTADO_SOLICITADA) {
            throw ValidationException::withMessages([
                'transferencia' => 'La transferencia ya no se encuentra en estado solicitada.',
            ]);
        }

        return DB::transaction(function () use ($transferencia, $actor, $datos): TransferenciaInventario {
            /** @var TransferenciaInventario $transfBloqueada */
            $transfBloqueada = TransferenciaInventario::query()->lockForUpdate()->findOrFail($transferencia->id);

            /** @var Almacen $origen */
            $origen = Almacen::query()->lockForUpdate()->findOrFail($transfBloqueada->almacen_origen_id);

            // Obtener o crear almacén virtual de tránsito
            /** @var Almacen $transito */
            $transito = Almacen::query()->firstOrCreate(
                [
                    'empresa_id' => $transfBloqueada->empresa_id,
                    'tipo' => Almacen::TIPO_TRANSITO,
                ],
                [
                    'codigo' => 'TRF-TRANSITO',
                    'nombre' => 'Almacén Virtual de Traspaso / Tránsito',
                    'es_virtual' => true,
                    'permite_existencias' => true,
                    'activo' => true,
                    'creado_por_id' => $actor->id,
                ]
            );

            $ocurridoAt = now();
            $grupoId = $transfBloqueada->grupo_id;
            $costoTotal = '0.0000';

            $detalles = $transfBloqueada->detalles()->lockForUpdate()->get();
            $productoIds = $detalles->pluck('producto_id')->all();
            sort($productoIds);
            Producto::query()->whereIn('id', $productoIds)->lockForUpdate()->get();

            // Mapear líneas provistas o usar cantidad solicitada por defecto
            $despachoPorDetalle = [];
            if (! empty($datos['lineas'])) {
                foreach ($datos['lineas'] as $l) {
                    $despachoPorDetalle[(int) $l['detalle_id']] = (string) $l['cantidad_despachada'];
                }
            }

            foreach ($detalles as $detalle) {
                $cantidadDespacho = isset($despachoPorDetalle[$detalle->id])
                    ? $despachoPorDetalle[$detalle->id]
                    : (string) $detalle->cantidad_solicitada;

                if (bccomp($cantidadDespacho, '0.0000', 4) !== 1) {
                    throw ValidationException::withMessages([
                        'lineas' => "La cantidad despachada para {$detalle->producto->nombre} debe ser mayor a cero.",
                    ]);
                }

                $productoId = $detalle->producto_id;

                /** @var ExistenciaAlmacen|null $existenciaOrigen */
                $existenciaOrigen = ExistenciaAlmacen::query()
                    ->where('empresa_id', $transfBloqueada->empresa_id)
                    ->where('almacen_id', $origen->id)
                    ->where('producto_id', $productoId)
                    ->lockForUpdate()
                    ->first();

                $cantidadDisponible = $existenciaOrigen !== null
                    ? bcsub((string) $existenciaOrigen->cantidad, (string) $existenciaOrigen->cantidad_reservada, 4)
                    : '0.0000';

                if (bccomp($cantidadDisponible, $cantidadDespacho, 4) === -1) {
                    throw ValidationException::withMessages([
                        'lineas' => "Stock insuficiente en {$origen->nombre} para {$detalle->producto->nombre}. Disponible: {$cantidadDisponible}",
                    ]);
                }

                $costoUnitario = (string) $existenciaOrigen->costo_promedio;
                $subtotal = bcmul($cantidadDespacho, $costoUnitario, 4);
                $costoTotal = bcadd($costoTotal, $subtotal, 4);

                // 1. Actualizar línea de detalle
                $detalle->update([
                    'cantidad' => $cantidadDespacho,
                    'cantidad_despachada' => $cantidadDespacho,
                    'costo_unitario' => $costoUnitario,
                    'subtotal' => $subtotal,
                ]);

                // 2. Descontar stock del almacén de origen
                $cantOrigenAnterior = (string) $existenciaOrigen->cantidad;
                $cantOrigenNueva = bcsub($cantOrigenAnterior, $cantidadDespacho, 4);

                $existenciaOrigen->update([
                    'cantidad' => $cantOrigenNueva,
                    'actualizado_por_id' => $actor->id,
                ]);

                MovimientoInventario::query()->create([
                    'empresa_id' => $transfBloqueada->empresa_id,
                    'almacen_id' => $origen->id,
                    'producto_id' => $productoId,
                    'grupo_id' => $grupoId,
                    'folio' => 'MOV-'.now()->format('YmdHisv').'-'.Str::upper(Str::random(6)),
                    'tipo' => MovimientoInventario::TIPO_TRANSFERENCIA_SALIDA,
                    'cantidad' => $cantidadDespacho,
                    'existencia_anterior' => $cantOrigenAnterior,
                    'existencia_nueva' => $cantOrigenNueva,
                    'costo_unitario' => $costoUnitario,
                    'referencia_tipo' => TransferenciaInventario::class,
                    'referencia_id' => $transfBloqueada->id,
                    'motivo' => "Despacho a tránsito hacia destino: {$transfBloqueada->motivo}",
                    'usuario_id' => $actor->id,
                    'ocurrido_at' => $ocurridoAt,
                ]);

                // 3. Ingresar al almacén de tránsito
                /** @var ExistenciaAlmacen|null $existenciaTransito */
                $existenciaTransito = ExistenciaAlmacen::query()
                    ->where('empresa_id', $transfBloqueada->empresa_id)
                    ->where('almacen_id', $transito->id)
                    ->where('producto_id', $productoId)
                    ->lockForUpdate()
                    ->first();

                $cantTransitoAnterior = $existenciaTransito !== null ? (string) $existenciaTransito->cantidad : '0.0000';
                $cantTransitoNueva = bcadd($cantTransitoAnterior, $cantidadDespacho, 4);

                if ($existenciaTransito === null) {
                    ExistenciaAlmacen::query()->create([
                        'empresa_id' => $transfBloqueada->empresa_id,
                        'almacen_id' => $transito->id,
                        'producto_id' => $productoId,
                        'cantidad' => $cantTransitoNueva,
                        'cantidad_reservada' => '0.0000',
                        'costo_promedio' => $costoUnitario,
                        'actualizado_por_id' => $actor->id,
                    ]);
                } else {
                    $existenciaTransito->update([
                        'cantidad' => $cantTransitoNueva,
                        'actualizado_por_id' => $actor->id,
                    ]);
                }

                MovimientoInventario::query()->create([
                    'empresa_id' => $transfBloqueada->empresa_id,
                    'almacen_id' => $transito->id,
                    'producto_id' => $productoId,
                    'grupo_id' => $grupoId,
                    'folio' => 'MOV-'.now()->format('YmdHisv').'-'.Str::upper(Str::random(6)),
                    'tipo' => MovimientoInventario::TIPO_TRANSFERENCIA_TRANSITO,
                    'cantidad' => $cantidadDespacho,
                    'existencia_anterior' => $cantTransitoAnterior,
                    'existencia_nueva' => $cantTransitoNueva,
                    'costo_unitario' => $costoUnitario,
                    'referencia_tipo' => TransferenciaInventario::class,
                    'referencia_id' => $transfBloqueada->id,
                    'motivo' => "Ingreso a traspaso/tránsito de solicitud {$transfBloqueada->folio}",
                    'usuario_id' => $actor->id,
                    'ocurrido_at' => $ocurridoAt,
                ]);
            }

            $transfBloqueada->update([
                'almacen_transito_id' => $transito->id,
                'estado' => TransferenciaInventario::ESTADO_EN_TRANSITO,
                'costo_total' => $costoTotal,
                'despachado_por_id' => $actor->id,
                'despachado_at' => $ocurridoAt,
                'observaciones_despacho' => $datos['observaciones'] ?? null,
            ]);

            return $transfBloqueada->fresh()->load([
                'empresa',
                'almacenOrigen',
                'almacenDestino',
                'almacenTransito',
                'despachadoPor',
                'detalles.producto',
            ]);
        }, 3);
    }
}
