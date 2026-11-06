<?php

namespace App\Actions\Inventario;

use App\Models\Almacen;
use App\Models\Empresa;
use App\Models\ExistenciaAlmacen;
use App\Models\MovimientoInventario;
use App\Models\OrdenCompra;
use App\Models\OrdenCompraDetalle;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\RecepcionInventario;
use App\Models\RecepcionInventarioDetalle;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RegistrarRecepcionInventario
{
    // ---------------------------------------------------------
    // REGISTRAR RECEPCIÓN
    // Normaliza y valida un documento que puede contener una o
    // varias líneas de productos para un mismo almacén, admitiendo
    // opcionalmente el enlace a una Orden de Compra previa.
    // ---------------------------------------------------------

    /**
     * @param  array{
     *     orden_compra_id?: mixed,
     *     tipo?: mixed,
     *     referencia_externa?: mixed,
     *     motivo?: mixed,
     *     lineas?: mixed
     * }  $datos
     */
    public function ejecutar(
        Empresa $empresa,
        Almacen $almacen,
        ?Proveedor $proveedor,
        User $actor,
        array $datos
    ): RecepcionInventario {
        $datosNormalizados = $this->normalizarDatos(
            $datos
        );

        $this->validar(
            empresa: $empresa,
            almacen: $almacen,
            proveedor: $proveedor,
            actor: $actor,
            datos: $datosNormalizados
        );

        return $this->registrarTransaccion(
            empresa: $empresa,
            almacen: $almacen,
            proveedor: $proveedor,
            actor: $actor,
            datos: $datosNormalizados
        );
    }

    // ---------------------------------------------------------
    // NORMALIZAR DOCUMENTO
    // Limpia el tipo, la orden de compra, la referencia, el motivo
    // y cada una de las líneas antes de aplicar las reglas.
    // ---------------------------------------------------------

    /**
     * @param  array<string, mixed>  $datos
     * @return array{
     *     orden_compra_id: int|null,
     *     tipo: string,
     *     referencia_externa: string|null,
     *     motivo: string,
     *     lineas: list<array{
     *         producto_id: int,
     *         cantidad: string,
     *         costo_unitario: string,
     *         subtotal: string
     *     }>
     * }
     */
    private function normalizarDatos(
        array $datos
    ): array {
        $ordenCompraId = filter_var(
            $datos['orden_compra_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        $tipo = $datos['tipo'] ?? '';
        $referenciaExterna = $datos['referencia_externa'] ?? null;
        $motivo = $datos['motivo'] ?? '';
        $lineasOriginales = $datos['lineas'] ?? [];

        $tipoNormalizado = is_string($tipo)
            ? Str::lower(trim($tipo))
            : '';

        $referenciaNormalizada = is_string($referenciaExterna) && trim($referenciaExterna) !== ''
            ? trim($referenciaExterna)
            : null;

        $motivoNormalizado = is_string($motivo)
            ? trim($motivo)
            : '';

        $lineasNormalizadas = [];

        if (is_array($lineasOriginales)) {
            foreach ($lineasOriginales as $linea) {
                if (! is_array($linea)) {
                    continue;
                }

                $productoId = filter_var(
                    $linea['producto_id'] ?? null,
                    FILTER_VALIDATE_INT
                );

                $cantidad = $this->normalizarDecimal(
                    $linea['cantidad'] ?? null
                );

                $costoUnitario = $this->normalizarDecimal(
                    $linea['costo_unitario'] ?? null
                );

                $subtotal = (
                    $cantidad !== ''
                    && $costoUnitario !== ''
                )
                    ? bcmul(
                        $cantidad,
                        $costoUnitario,
                        4
                    )
                    : '';

                $lineasNormalizadas[] = [
                    'producto_id' => $productoId !== false
                        ? (int) $productoId
                        : 0,
                    'cantidad' => $cantidad,
                    'costo_unitario' => $costoUnitario,
                    'subtotal' => $subtotal,
                ];
            }
        }

        return [
            'orden_compra_id' => $ordenCompraId !== false ? (int) $ordenCompraId : null,
            'tipo' => $tipoNormalizado,
            'referencia_externa' => $referenciaNormalizada,
            'motivo' => $motivoNormalizado,
            'lineas' => $lineasNormalizadas,
        ];
    }

    // ---------------------------------------------------------
    // NORMALIZAR DECIMAL
    // Convierte números a representación uniforme de 4 decimales.
    // ---------------------------------------------------------

    private function normalizarDecimal(
        mixed $valor
    ): string {
        if (
            ! is_int($valor)
            && ! is_float($valor)
            && ! is_string($valor)
        ) {
            return '';
        }

        if (
            ! is_numeric($valor)
            || ! is_finite((float) $valor)
        ) {
            return '';
        }

        return number_format(
            (float) $valor,
            4,
            '.',
            ''
        );
    }

    // ---------------------------------------------------------
    // VALIDACIÓN PRELIMINAR
    // Comprueba permisos, pertenencia empresarial, estados
    // y consistencia de las líneas antes de persistir.
    // ---------------------------------------------------------

    /**
     * @param  array{
     *     orden_compra_id: int|null,
     *     tipo: string,
     *     referencia_externa: string|null,
     *     motivo: string,
     *     lineas: list<array{
     *         producto_id: int,
     *         cantidad: string,
     *         costo_unitario: string,
     *         subtotal: string
     *     }>
     * }  $datos
     */
    private function validar(
        Empresa $empresa,
        Almacen $almacen,
        ?Proveedor $proveedor,
        User $actor,
        array $datos
    ): void {
        if (! $actor->can('inventario.registrar_entrada')) {
            throw new AuthorizationException(
                'No tienes permiso para registrar entradas de inventario.'
            );
        }

        if ($almacen->empresa_id !== $empresa->id) {
            throw new AuthorizationException(
                'El almacén no pertenece a la empresa indicada.'
            );
        }

        if (! $empresa->activo) {
            throw ValidationException::withMessages([
                'empresa' => 'No se pueden registrar recepciones para una empresa inactiva.',
            ]);
        }

        if (! $almacen->activo) {
            throw ValidationException::withMessages([
                'almacen' => 'No se pueden registrar recepciones en un almacén inactivo.',
            ]);
        }

        if (! $almacen->permite_existencias) {
            throw ValidationException::withMessages([
                'almacen' => 'El almacén seleccionado no permite manejar existencias.',
            ]);
        }

        if (! $almacen->es_virtual) {
            $sucursal = $almacen->sucursal;

            if ($sucursal === null) {
                throw ValidationException::withMessages([
                    'sucursal' => 'El almacén físico debe pertenecer a una sucursal.',
                ]);
            }

            if ($sucursal->empresa_id !== $empresa->id) {
                throw new AuthorizationException(
                    'La sucursal del almacén no pertenece a la empresa indicada.'
                );
            }

            if (! $sucursal->activo) {
                throw ValidationException::withMessages([
                    'sucursal' => 'No se pueden registrar recepciones en una sucursal inactiva.',
                ]);
            }
        }

        if (
            ! in_array(
                $datos['tipo'],
                RecepcionInventario::tiposPermitidos(),
                true
            )
        ) {
            throw ValidationException::withMessages([
                'tipo' => 'El tipo de recepción no es válido.',
            ]);
        }

        if (
            $datos['tipo'] === RecepcionInventario::TIPO_COMPRA
            && $proveedor === null
        ) {
            throw ValidationException::withMessages([
                'proveedor' => 'El proveedor es obligatorio para recepciones de tipo compra.',
            ]);
        }

        if ($proveedor !== null) {
            if ($proveedor->empresa_id !== $empresa->id) {
                throw new AuthorizationException(
                    'El proveedor no pertenece a la empresa indicada.'
                );
            }

            if (! $proveedor->activo) {
                throw ValidationException::withMessages([
                    'proveedor' => 'No se pueden registrar recepciones con un proveedor inactivo.',
                ]);
            }
        }

        // Validación de orden de compra si viene referenciada
        if ($datos['orden_compra_id'] !== null) {
            /** @var OrdenCompra|null $ordenCompra */
            $ordenCompra = OrdenCompra::query()->find($datos['orden_compra_id']);

            if ($ordenCompra === null) {
                throw ValidationException::withMessages([
                    'orden_compra_id' => 'La orden de compra especificada no existe.',
                ]);
            }

            if ($ordenCompra->empresa_id !== $empresa->id) {
                throw new AuthorizationException(
                    'La orden de compra no pertenece a la empresa indicada.'
                );
            }

            if (! in_array($ordenCompra->estado, ['autorizada', 'recibida_parcial'], true)) {
                throw ValidationException::withMessages([
                    'orden_compra_id' => 'Solo se pueden recibir órdenes en estado autorizada o recibida parcial.',
                ]);
            }
        }

        if (
            $datos['referencia_externa'] !== null
            && mb_strlen($datos['referencia_externa']) > 150
        ) {
            throw ValidationException::withMessages([
                'referencia_externa' => 'La referencia externa no debe superar los 150 caracteres.',
            ]);
        }

        if (
            $datos['motivo'] === ''
            || mb_strlen($datos['motivo']) > 1000
        ) {
            throw ValidationException::withMessages([
                'motivo' => 'Debes indicar un motivo válido de hasta 1000 caracteres.',
            ]);
        }

        if (empty($datos['lineas'])) {
            throw ValidationException::withMessages([
                'lineas' => 'La recepción debe incluir al menos un producto.',
            ]);
        }

        $productoIds = array_column(
            $datos['lineas'],
            'producto_id'
        );

        if (
            count($productoIds) !== count(array_unique($productoIds))
        ) {
            throw ValidationException::withMessages([
                'lineas' => 'No se pueden incluir productos duplicados en la misma recepción.',
            ]);
        }

        foreach ($datos['lineas'] as $indice => $linea) {
            if ($linea['producto_id'] <= 0) {
                throw ValidationException::withMessages([
                    "lineas.{$indice}.producto_id" => 'El producto seleccionado no es válido.',
                ]);
            }

            if (
                $linea['cantidad'] === ''
                || bccomp($linea['cantidad'], '0.0000', 4) !== 1
            ) {
                throw ValidationException::withMessages([
                    "lineas.{$indice}.cantidad" => 'La cantidad de entrada debe ser mayor que cero y tener hasta cuatro decimales.',
                ]);
            }

            if (
                $linea['costo_unitario'] === ''
                || bccomp($linea['costo_unitario'], '0.0000', 4) === -1
            ) {
                throw ValidationException::withMessages([
                    "lineas.{$indice}.costo_unitario" => 'El costo unitario debe ser igual o mayor que cero y tener hasta cuatro decimales.',
                ]);
            }
        }

        /** @var Collection<int, Producto> $productos */
        $productos = Producto::query()
            ->whereIn('id', $productoIds)
            ->get();

        foreach ($productoIds as $indice => $productoId) {
            /** @var Producto|null $producto */
            $producto = $productos->firstWhere('id', $productoId);

            if ($producto === null) {
                throw ValidationException::withMessages([
                    "lineas.{$indice}.producto_id" => 'Uno o más productos seleccionados no existen.',
                ]);
            }

            if ($producto->empresa_id !== $empresa->id) {
                throw new AuthorizationException(
                    'Uno o más productos no pertenecen a la empresa indicada.'
                );
            }

            if (! $producto->activo) {
                throw ValidationException::withMessages([
                    "lineas.{$indice}.producto_id" => "El producto {$producto->nombre} está inactivo.",
                ]);
            }
        }
    }

    // ---------------------------------------------------------
    // REGISTRAR TRANSACCIÓN
    // Ejecuta de forma atómica la creación del documento,
    // sus líneas, las existencias, los movimientos y actualiza
    // la orden de compra si viene referenciada.
    // ---------------------------------------------------------

    /**
     * @param  array{
     *     orden_compra_id: int|null,
     *     tipo: string,
     *     referencia_externa: string|null,
     *     motivo: string,
     *     lineas: list<array{
     *         producto_id: int,
     *         cantidad: string,
     *         costo_unitario: string,
     *         subtotal: string
     *     }>
     * }  $datos
     */
    private function registrarTransaccion(
        Empresa $empresa,
        Almacen $almacen,
        ?Proveedor $proveedor,
        User $actor,
        array $datos
    ): RecepcionInventario {
        return DB::transaction(
            function () use (
                $empresa,
                $almacen,
                $proveedor,
                $actor,
                $datos
            ): RecepcionInventario {
                $empresaBloqueada = Empresa::query()
                    ->lockForUpdate()
                    ->findOrFail($empresa->id);

                $almacenBloqueado = Almacen::query()
                    ->lockForUpdate()
                    ->findOrFail($almacen->id);

                $proveedorBloqueado = $proveedor !== null
                    ? Proveedor::query()
                        ->lockForUpdate()
                        ->findOrFail($proveedor->id)
                    : null;

                $ordenCompraBloqueada = $datos['orden_compra_id'] !== null
                    ? OrdenCompra::query()
                        ->with(['detalles'])
                        ->lockForUpdate()
                        ->findOrFail($datos['orden_compra_id'])
                    : null;

                $productoIds = array_column(
                    $datos['lineas'],
                    'producto_id'
                );

                sort($productoIds);

                Producto::query()
                    ->whereIn('id', $productoIds)
                    ->orderBy('id', 'asc')
                    ->lockForUpdate()
                    ->get();

                $this->validar(
                    empresa: $empresaBloqueada,
                    almacen: $almacenBloqueado,
                    proveedor: $proveedorBloqueado,
                    actor: $actor,
                    datos: $datos
                );

                $grupoId = (string) Str::uuid();
                $folioRecepcion = $this->generarFolioRecepcion();
                $recibidoAt = now();

                $totalLineas = count($datos['lineas']);
                $importeTotal = '0.0000';

                foreach ($datos['lineas'] as $linea) {
                    $importeTotal = bcadd(
                        $importeTotal,
                        $linea['subtotal'],
                        4
                    );
                }

                $tipoMovimiento = match ($datos['tipo']) {
                    RecepcionInventario::TIPO_DEVOLUCION => MovimientoInventario::TIPO_DEVOLUCION,
                    RecepcionInventario::TIPO_AJUSTE_POSITIVO => MovimientoInventario::TIPO_AJUSTE_POSITIVO,
                    default => MovimientoInventario::TIPO_ENTRADA,
                };

                /** @var RecepcionInventario $recepcion */
                $recepcion = RecepcionInventario::query()->create([
                    'empresa_id' => $empresaBloqueada->id,
                    'almacen_id' => $almacenBloqueado->id,
                    'proveedor_id' => $proveedorBloqueado?->id,
                    'orden_compra_id' => $ordenCompraBloqueada?->id,
                    'grupo_id' => $grupoId,
                    'folio' => $folioRecepcion,
                    'tipo' => $datos['tipo'],
                    'referencia_externa' => $datos['referencia_externa'],
                    'motivo' => $datos['motivo'],
                    'total_lineas' => $totalLineas,
                    'importe_total' => $importeTotal,
                    'recibido_por_id' => $actor->id,
                    'recibido_at' => $recibidoAt,
                ]);

                foreach ($datos['lineas'] as $linea) {
                    $productoId = $linea['producto_id'];
                    $cantidadEntrada = $linea['cantidad'];
                    $costoEntrada = $linea['costo_unitario'];
                    $subtotal = $linea['subtotal'];

                    RecepcionInventarioDetalle::query()->create([
                        'recepcion_inventario_id' => $recepcion->id,
                        'producto_id' => $productoId,
                        'cantidad' => $cantidadEntrada,
                        'costo_unitario' => $costoEntrada,
                        'subtotal' => $subtotal,
                    ]);

                    /** @var ExistenciaAlmacen|null $existencia */
                    $existencia = ExistenciaAlmacen::query()
                        ->where(
                            'empresa_id',
                            $empresaBloqueada->id
                        )
                        ->where(
                            'almacen_id',
                            $almacenBloqueado->id
                        )
                        ->where(
                            'producto_id',
                            $productoId
                        )
                        ->lockForUpdate()
                        ->first();

                    $cantidadAnterior = $existencia !== null
                        ? (string) $existencia->cantidad
                        : '0.0000';

                    $costoPromedioAnterior = $existencia !== null
                        ? (string) $existencia->costo_promedio
                        : '0.0000';

                    $cantidadNueva = bcadd(
                        $cantidadAnterior,
                        $cantidadEntrada,
                        4
                    );

                    $valorAnterior = bcmul(
                        $cantidadAnterior,
                        $costoPromedioAnterior,
                        8
                    );

                    $valorEntrada = bcmul(
                        $cantidadEntrada,
                        $costoEntrada,
                        8
                    );

                    $valorTotal = bcadd(
                        $valorAnterior,
                        $valorEntrada,
                        8
                    );

                    $costoPromedioNuevo = bcdiv(
                        $valorTotal,
                        $cantidadNueva,
                        4
                    );

                    if ($existencia === null) {
                        ExistenciaAlmacen::query()->create([
                            'empresa_id' => $empresaBloqueada->id,
                            'almacen_id' => $almacenBloqueado->id,
                            'producto_id' => $productoId,
                            'cantidad' => $cantidadNueva,
                            'cantidad_reservada' => '0.0000',
                            'costo_promedio' => $costoPromedioNuevo,
                            'actualizado_por_id' => $actor->id,
                        ]);
                    } else {
                        $existencia->update([
                            'cantidad' => $cantidadNueva,
                            'costo_promedio' => $costoPromedioNuevo,
                            'actualizado_por_id' => $actor->id,
                        ]);
                    }

                    $folioMovimiento = $this->generarFolioMovimiento();

                    MovimientoInventario::query()->create([
                        'empresa_id' => $empresaBloqueada->id,
                        'almacen_id' => $almacenBloqueado->id,
                        'producto_id' => $productoId,
                        'grupo_id' => $grupoId,
                        'folio' => $folioMovimiento,
                        'tipo' => $tipoMovimiento,
                        'cantidad' => $cantidadEntrada,
                        'existencia_anterior' => $cantidadAnterior,
                        'existencia_nueva' => $cantidadNueva,
                        'costo_unitario' => $costoEntrada,
                        'referencia_tipo' => RecepcionInventario::class,
                        'referencia_id' => $recepcion->id,
                        'motivo' => $datos['motivo'],
                        'usuario_id' => $actor->id,
                        'ocurrido_at' => $recibidoAt,
                    ]);
                }

                // ---------------------------------------------
                // ACTUALIZACIÓN DE ORDEN DE COMPRA
                // Acumula la cantidad recibida por partida y
                // recalcula el estado de entrega de la orden.
                // ---------------------------------------------

                if ($ordenCompraBloqueada !== null) {
                    foreach ($datos['lineas'] as $linea) {
                        /** @var OrdenCompraDetalle|null $detalleOrden */
                        $detalleOrden = $ordenCompraBloqueada->detalles
                            ->firstWhere('producto_id', $linea['producto_id']);

                        if ($detalleOrden !== null) {
                            $nuevaCantidadRecibida = bcadd(
                                (string) $detalleOrden->cantidad_recibida,
                                $linea['cantidad'],
                                4
                            );

                            $detalleOrden->update([
                                'cantidad_recibida' => $nuevaCantidadRecibida,
                            ]);
                        }
                    }

                    $ordenCompraBloqueada->refresh();
                    $completada = true;
                    /** @var OrdenCompraDetalle $det */
                    foreach ($ordenCompraBloqueada->detalles as $det) {
                        if (bccomp((string) $det->cantidad_recibida, (string) $det->cantidad, 4) < 0) {
                            $completada = false;
                            break;
                        }
                    }

                    $ordenCompraBloqueada->update([
                        'estado' => $completada ? 'recibida_total' : 'recibida_parcial',
                    ]);
                }

                $recepcionActualizada = $recepcion->fresh();

                if ($recepcionActualizada === null) {
                    throw ValidationException::withMessages([
                        'recepcion' => 'No fue posible recuperar la recepción registrada.',
                    ]);
                }

                return $recepcionActualizada->load([
                    'empresa',
                    'almacen',
                    'proveedor',
                    'ordenCompra',
                    'recibidoPor',
                    'detalles.producto',
                ]);
            },
            3
        );
    }

    // ---------------------------------------------------------
    // GENERAR FOLIO DE RECEPCIÓN
    // ---------------------------------------------------------

    private function generarFolioRecepcion(): string
    {
        return 'REC-'
            .now()->format('YmdHisv')
            .Str::upper(
                Str::random(6)
            );
    }

    // ---------------------------------------------------------
    // GENERAR FOLIO DE MOVIMIENTO
    // ---------------------------------------------------------

    private function generarFolioMovimiento(): string
    {
        return 'MOV-'
            .now()->format('YmdHisv')
            .Str::upper(
                Str::random(6)
            );
    }
}
