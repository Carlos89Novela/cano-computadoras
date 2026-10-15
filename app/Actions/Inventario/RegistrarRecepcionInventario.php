<?php

namespace App\Actions\Inventario;

use App\Models\Almacen;
use App\Models\Empresa;
use App\Models\ExistenciaAlmacen;
use App\Models\MovimientoInventario;
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
    // varias líneas de productos para un mismo almacén.
    // ---------------------------------------------------------

    /**
     * @param  array{
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
    // Limpia el tipo, la referencia, el motivo y cada una de
    // las líneas antes de aplicar las reglas de negocio.
    // ---------------------------------------------------------

    /**
     * @param  array<string, mixed>  $datos
     * @return array{
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
            'tipo' => $tipoNormalizado,
            'referencia_externa' => $referenciaNormalizada,
            'motivo' => $motivoNormalizado,
            'lineas' => $lineasNormalizadas,
        ];
    }

    // ---------------------------------------------------------
    // NORMALIZAR DECIMAL
    // Convierte números válidos a una representación uniforme
    // con cuatro posiciones decimales.
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
        // -----------------------------------------------------
        // PERMISO
        // El usuario requiere el permiso de registrar entrada.
        // -----------------------------------------------------

        if (
            ! $actor->can(
                'inventario.registrar_entrada'
            )
        ) {
            throw new AuthorizationException(
                'No tienes permiso para registrar entradas de inventario.'
            );
        }

        // -----------------------------------------------------
        // ALMACÉN DE LA EMPRESA
        // El almacén debe pertenecer a la empresa indicada.
        // -----------------------------------------------------

        if ($almacen->empresa_id !== $empresa->id) {
            throw new AuthorizationException(
                'El almacén no pertenece a la empresa indicada.'
            );
        }

        // -----------------------------------------------------
        // EMPRESA ACTIVA
        // La empresa no debe encontrarse inactiva.
        // -----------------------------------------------------

        if (! $empresa->activo) {
            throw ValidationException::withMessages([
                'empresa' => 'No se pueden registrar recepciones para una empresa inactiva.',
            ]);
        }

        // -----------------------------------------------------
        // ALMACÉN ACTIVO
        // El almacén debe estar habilitado operativamente.
        // -----------------------------------------------------

        if (! $almacen->activo) {
            throw ValidationException::withMessages([
                'almacen' => 'No se pueden registrar recepciones en un almacén inactivo.',
            ]);
        }

        // -----------------------------------------------------
        // ALMACÉN CON EXISTENCIAS
        // El almacén debe permitir administrar existencias.
        // -----------------------------------------------------

        if (! $almacen->permite_existencias) {
            throw ValidationException::withMessages([
                'almacen' => 'El almacén seleccionado no permite manejar existencias.',
            ]);
        }

        // -----------------------------------------------------
        // SUCURSAL DEL ALMACÉN FÍSICO
        // Los almacenes físicos deben pertenecer a una sucursal
        // activa de la misma empresa.
        // -----------------------------------------------------

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

        // -----------------------------------------------------
        // TIPO DE RECEPCIÓN
        // Debe ser uno de los tipos permitidos por el modelo.
        // -----------------------------------------------------

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

        // -----------------------------------------------------
        // PROVEEDOR
        // Obligatorio en compras y opcional en los demás tipos.
        // Debe pertenecer a la empresa y encontrarse activo.
        // -----------------------------------------------------

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

        // -----------------------------------------------------
        // REFERENCIA EXTERNA
        // Longitud máxima de 150 caracteres si se proporciona.
        // -----------------------------------------------------

        if (
            $datos['referencia_externa'] !== null
            && mb_strlen($datos['referencia_externa']) > 150
        ) {
            throw ValidationException::withMessages([
                'referencia_externa' => 'La referencia externa no debe superar los 150 caracteres.',
            ]);
        }

        // -----------------------------------------------------
        // MOTIVO
        // Justificación requerida de hasta 1000 caracteres.
        // -----------------------------------------------------

        if (
            $datos['motivo'] === ''
            || mb_strlen($datos['motivo']) > 1000
        ) {
            throw ValidationException::withMessages([
                'motivo' => 'Debes indicar un motivo válido de hasta 1000 caracteres.',
            ]);
        }

        // -----------------------------------------------------
        // LÍNEAS
        // Debe existir al menos una línea sin productos repetidos.
        // -----------------------------------------------------

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
    // sus líneas, las existencias y los movimientos.
    // ---------------------------------------------------------

    /**
     * @param  array{
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
                // ---------------------------------------------
                // BLOQUEO DE ENTIDADES PRINCIPALES
                // Evita modificaciones concurrentes de empresa,
                // almacén y proveedor.
                // ---------------------------------------------

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

                // ---------------------------------------------
                // BLOQUEO DE PRODUCTOS
                // Ordena los IDs para prevenir bloqueos mutuos
                // en concurrencia.
                // ---------------------------------------------

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

                // ---------------------------------------------
                // REVALIDACIÓN BAJO BLOQUEO
                // Asegura la consistencia con el estado actual.
                // ---------------------------------------------

                $this->validar(
                    empresa: $empresaBloqueada,
                    almacen: $almacenBloqueado,
                    proveedor: $proveedorBloqueado,
                    actor: $actor,
                    datos: $datos
                );

                // ---------------------------------------------
                // TOTALES Y AGRUPADORES
                // Genera UUID compartido y calcula importe total.
                // ---------------------------------------------

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

                // ---------------------------------------------
                // CREACIÓN DEL ENCABEZADO
                // Persiste el documento maestro de la recepción.
                // ---------------------------------------------

                /** @var RecepcionInventario $recepcion */
                $recepcion = RecepcionInventario::query()->create([
                    'empresa_id' => $empresaBloqueada->id,
                    'almacen_id' => $almacenBloqueado->id,
                    'proveedor_id' => $proveedorBloqueado?->id,
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

                // ---------------------------------------------
                // PROCESAMIENTO DE LÍNEAS
                // Registra cada detalle, actualiza la existencia
                // y asienta el movimiento correspondiente.
                // ---------------------------------------------

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
                    'recibidoPor',
                    'detalles.producto',
                ]);
            },
            3
        );
    }

    // ---------------------------------------------------------
    // GENERAR FOLIO DE RECEPCIÓN
    // Genera un identificador legible y único para el documento.
    // ---------------------------------------------------------

    private function generarFolioRecepcion(): string
    {
        return 'REC-'
            .now()->format('YmdHisv')
            .'-'
            .Str::upper(
                Str::random(6)
            );
    }

    // ---------------------------------------------------------
    // GENERAR FOLIO DE MOVIMIENTO
    // Genera un identificador único para cada movimiento de línea.
    // ---------------------------------------------------------

    private function generarFolioMovimiento(): string
    {
        return 'MOV-'
            .now()->format('YmdHisv')
            .'-'
            .Str::upper(
                Str::random(6)
            );
    }
}
