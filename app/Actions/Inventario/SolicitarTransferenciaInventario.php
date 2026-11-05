<?php

namespace App\Actions\Inventario;

use App\Models\Almacen;
use App\Models\Empresa;
use App\Models\ExistenciaAlmacen;
use App\Models\Producto;
use App\Models\TransferenciaInventario;
use App\Models\TransferenciaInventarioDetalle;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SolicitarTransferenciaInventario
{
    /**
     * @param  array{
     *     motivo?: mixed,
     *     lineas?: mixed
     * }  $datos
     */
    public function ejecutar(
        Empresa $empresa,
        Almacen $origen,
        Almacen $destino,
        User $actor,
        array $datos
    ): TransferenciaInventario {
        $datosNormalizados = $this->normalizarDatos($datos);

        $this->validar(
            empresa: $empresa,
            origen: $origen,
            destino: $destino,
            actor: $actor,
            datos: $datosNormalizados
        );

        return DB::transaction(function () use ($empresa, $origen, $destino, $actor, $datosNormalizados): TransferenciaInventario {
            $empresaBloqueada = Empresa::query()->lockForUpdate()->findOrFail($empresa->id);
            $origenBloqueado = Almacen::query()->lockForUpdate()->findOrFail($origen->id);
            $destinoBloqueado = Almacen::query()->lockForUpdate()->findOrFail($destino->id);

            $this->validar($empresaBloqueada, $origenBloqueado, $destinoBloqueado, $actor, $datosNormalizados);

            $productoIds = array_column($datosNormalizados['lineas'], 'producto_id');
            sort($productoIds);
            Producto::query()->whereIn('id', $productoIds)->lockForUpdate()->get();

            $grupoId = (string) Str::uuid();
            $folio = 'TRF-'.now()->format('YmdHisv').'-'.Str::upper(Str::random(6));
            $ocurridoAt = now();
            $costoTotal = '0.0000';

            /** @var TransferenciaInventario $transferencia */
            $transferencia = TransferenciaInventario::query()->create([
                'empresa_id' => $empresaBloqueada->id,
                'almacen_origen_id' => $origenBloqueado->id,
                'almacen_destino_id' => $destinoBloqueado->id,
                'grupo_id' => $grupoId,
                'folio' => $folio,
                'estado' => TransferenciaInventario::ESTADO_SOLICITADA,
                'motivo' => $datosNormalizados['motivo'],
                'total_lineas' => count($datosNormalizados['lineas']),
                'costo_total' => '0.0000',
                'creado_por_id' => $actor->id,
                'solicitado_por_id' => $actor->id,
                'transferido_at' => $ocurridoAt,
                'solicitado_at' => $ocurridoAt,
            ]);

            foreach ($datosNormalizados['lineas'] as $i => $linea) {
                $productoId = $linea['producto_id'];
                $cantidad = $linea['cantidad'];

                /** @var ExistenciaAlmacen|null $existenciaOrigen */
                $existenciaOrigen = ExistenciaAlmacen::query()
                    ->where('empresa_id', $empresaBloqueada->id)
                    ->where('almacen_id', $origenBloqueado->id)
                    ->where('producto_id', $productoId)
                    ->first();

                $cantidadDisponible = $existenciaOrigen !== null
                    ? bcsub((string) $existenciaOrigen->cantidad, (string) $existenciaOrigen->cantidad_reservada, 4)
                    : '0.0000';

                if (bccomp($cantidadDisponible, $cantidad, 4) === -1) {
                    throw ValidationException::withMessages([
                        "lineas.{$i}.cantidad" => "Stock disponible insuficiente en {$origenBloqueado->nombre}. Disponible: {$cantidadDisponible}",
                    ]);
                }

                $costoUnitario = $existenciaOrigen !== null ? (string) $existenciaOrigen->costo_promedio : '0.0000';
                $subtotal = bcmul($cantidad, $costoUnitario, 4);
                $costoTotal = bcadd($costoTotal, $subtotal, 4);

                TransferenciaInventarioDetalle::query()->create([
                    'transferencia_inventario_id' => $transferencia->id,
                    'producto_id' => $productoId,
                    'cantidad' => $cantidad,
                    'cantidad_solicitada' => $cantidad,
                    'cantidad_despachada' => '0.0000',
                    'cantidad_recibida' => '0.0000',
                    'costo_unitario' => $costoUnitario,
                    'subtotal' => $subtotal,
                ]);
            }

            $transferencia->update([
                'costo_total' => $costoTotal,
            ]);

            return $transferencia->fresh()->load([
                'empresa',
                'almacenOrigen',
                'almacenDestino',
                'solicitadoPor',
                'detalles.producto',
            ]);
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array{
     *     motivo: string,
     *     lineas: list<array{
     *         producto_id: int,
     *         cantidad: string
     *     }>
     * }
     */
    private function normalizarDatos(array $datos): array
    {
        $motivo = $datos['motivo'] ?? '';
        $lineasOriginales = $datos['lineas'] ?? [];
        $lineasNormalizadas = [];

        if (is_array($lineasOriginales)) {
            foreach ($lineasOriginales as $linea) {
                if (! is_array($linea)) {
                    continue;
                }

                $productoId = filter_var($linea['producto_id'] ?? null, FILTER_VALIDATE_INT);
                $cantidad = $this->normalizarDecimal($linea['cantidad'] ?? null);

                $lineasNormalizadas[] = [
                    'producto_id' => $productoId !== false ? (int) $productoId : 0,
                    'cantidad' => $cantidad,
                ];
            }
        }

        return [
            'motivo' => is_string($motivo) ? trim($motivo) : '',
            'lineas' => $lineasNormalizadas,
        ];
    }

    private function normalizarDecimal(mixed $valor): string
    {
        if (! is_int($valor) && ! is_float($valor) && ! is_string($valor)) {
            return '';
        }

        if (! is_numeric($valor) || ! is_finite((float) $valor)) {
            return '';
        }

        return number_format((float) $valor, 4, '.', '');
    }

    /**
     * @param  array{
     *     motivo: string,
     *     lineas: list<array{
     *         producto_id: int,
     *         cantidad: string
     *     }>
     * }  $datos
     */
    private function validar(
        Empresa $empresa,
        Almacen $origen,
        Almacen $destino,
        User $actor,
        array $datos
    ): void {
        if (! $actor->can('inventario.transferir') && ! $actor->hasRole('administrador')) {
            throw new AuthorizationException('No tienes permiso para solicitar transferencias.');
        }

        if ($origen->id === $destino->id) {
            throw ValidationException::withMessages([
                'almacen_destino_id' => 'El almacén de destino no puede ser igual al de origen.',
            ]);
        }

        if ($origen->empresa_id !== $empresa->id || $destino->empresa_id !== $empresa->id) {
            throw new AuthorizationException('Ambos almacenes deben pertenecer a la empresa.');
        }

        if (! $empresa->activo) {
            throw ValidationException::withMessages([
                'empresa' => 'La empresa está inactiva.',
            ]);
        }

        if (! $origen->activo || ! $origen->permite_existencias) {
            throw ValidationException::withMessages([
                'almacen_origen_id' => 'El almacén de origen se encuentra inactivo o no maneja existencias.',
            ]);
        }

        if (! $destino->activo || ! $destino->permite_existencias) {
            throw ValidationException::withMessages([
                'almacen_destino_id' => 'El almacén de destino se encuentra inactivo o no maneja existencias.',
            ]);
        }

        if ($datos['motivo'] === '' || mb_strlen($datos['motivo']) > 1000) {
            throw ValidationException::withMessages([
                'motivo' => 'Debes indicar un motivo válido de hasta 1000 caracteres.',
            ]);
        }

        if (empty($datos['lineas'])) {
            throw ValidationException::withMessages([
                'lineas' => 'Debes incluir al menos un producto en la requisición.',
            ]);
        }

        $productoIds = array_column($datos['lineas'], 'producto_id');

        if (count($productoIds) !== count(array_unique($productoIds))) {
            throw ValidationException::withMessages([
                'lineas' => 'No puedes solicitar productos duplicados en el mismo documento.',
            ]);
        }

        foreach ($datos['lineas'] as $i => $linea) {
            if ($linea['producto_id'] <= 0) {
                throw ValidationException::withMessages([
                    "lineas.{$i}.producto_id" => 'El producto seleccionado no es válido.',
                ]);
            }

            if ($linea['cantidad'] === '' || bccomp($linea['cantidad'], '0.0000', 4) !== 1) {
                throw ValidationException::withMessages([
                    "lineas.{$i}.cantidad" => 'La cantidad debe ser mayor a cero.',
                ]);
            }
        }
    }
}
