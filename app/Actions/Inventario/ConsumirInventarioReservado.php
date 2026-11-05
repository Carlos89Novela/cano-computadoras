<?php

namespace App\Actions\Inventario;

use App\Models\Almacen;
use App\Models\Empresa;
use App\Models\ExistenciaAlmacen;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ConsumirInventarioReservado
{
    // ---------------------------------------------------------
    // CONSUMIR INVENTARIO RESERVADO
    // Descuenta simultáneamente la existencia física y las
    // unidades reservadas utilizadas en una operación.
    // ---------------------------------------------------------

    /**
     * @param  array<string, mixed>  $datos
     */
    public function ejecutar(
        Empresa $empresa,
        Almacen $almacen,
        Producto $producto,
        User $actor,
        array $datos
    ): ExistenciaAlmacen {
        // -----------------------------------------------------
        // NORMALIZACIÓN
        // Estandariza cantidad, motivo y referencias antes de
        // validar el consumo reservado.
        // -----------------------------------------------------

        $datosNormalizados = $this->normalizarDatos(
            $datos
        );

        // -----------------------------------------------------
        // VALIDACIÓN PRELIMINAR
        // Comprueba autorización, pertenencia empresarial,
        // estados operativos y formato de los datos.
        // -----------------------------------------------------

        $this->validar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            actor: $actor,
            datos: $datosNormalizados
        );

        // -----------------------------------------------------
        // TRANSACCIÓN
        // La actualización de la existencia y la creación del
        // movimiento deben completarse como una sola operación.
        // -----------------------------------------------------

        return DB::transaction(
            function () use (
                $empresa,
                $almacen,
                $producto,
                $actor,
                $datosNormalizados
            ): ExistenciaAlmacen {
                // ---------------------------------------------
                // BLOQUEO DE ENTIDADES
                // Recupera empresa, almacén y producto bajo
                // bloqueo antes de modificar el inventario.
                // ---------------------------------------------

                $empresaBloqueada = Empresa::query()
                    ->lockForUpdate()
                    ->findOrFail($empresa->id);

                $almacenBloqueado = Almacen::query()
                    ->lockForUpdate()
                    ->findOrFail($almacen->id);

                $productoBloqueado = Producto::query()
                    ->lockForUpdate()
                    ->findOrFail($producto->id);

                // ---------------------------------------------
                // REVALIDACIÓN
                // Confirma las reglas utilizando los registros
                // recuperados bajo bloqueo.
                // ---------------------------------------------

                $this->validar(
                    empresa: $empresaBloqueada,
                    almacen: $almacenBloqueado,
                    producto: $productoBloqueado,
                    actor: $actor,
                    datos: $datosNormalizados
                );

                // ---------------------------------------------
                // EXISTENCIA BLOQUEADA
                // Localiza el registro exacto e impide consumos
                // concurrentes sobre la misma existencia.
                // ---------------------------------------------

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
                        $productoBloqueado->id
                    )
                    ->lockForUpdate()
                    ->first();

                if ($existencia === null) {
                    throw ValidationException::withMessages([
                        'existencia' => 'El producto no tiene existencia registrada en el almacén.',
                    ]);
                }

                $cantidadConsumir = (string) (
                    $datosNormalizados['cantidad']
                );

                $cantidadAnterior = (string) (
                    $existencia->cantidad
                );

                $cantidadReservadaAnterior = (string) (
                    $existencia->cantidad_reservada
                );

                $costoPromedio = (string) (
                    $existencia->costo_promedio
                );

                $motivo = (string) (
                    $datosNormalizados['motivo']
                );

                // ---------------------------------------------
                // RESERVA SUFICIENTE
                // El consumo solamente puede utilizar unidades
                // que se encuentren previamente reservadas.
                // ---------------------------------------------

                if (
                    bccomp(
                        $cantidadConsumir,
                        $cantidadReservadaAnterior,
                        4
                    ) === 1
                ) {
                    throw ValidationException::withMessages([
                        'cantidad' => 'La cantidad solicitada supera las unidades reservadas.',
                    ]);
                }

                // ---------------------------------------------
                // EXISTENCIA FÍSICA SUFICIENTE
                // Protege contra una inconsistencia previa en
                // la que la reserva sea mayor al saldo físico.
                // ---------------------------------------------

                if (
                    bccomp(
                        $cantidadConsumir,
                        $cantidadAnterior,
                        4
                    ) === 1
                ) {
                    throw ValidationException::withMessages([
                        'cantidad' => 'La cantidad solicitada supera la existencia física.',
                    ]);
                }

                // ---------------------------------------------
                // NUEVOS SALDOS
                // Reduce en la misma cantidad la existencia
                // física y el total de unidades reservadas.
                // ---------------------------------------------

                $cantidadNueva = bcsub(
                    $cantidadAnterior,
                    $cantidadConsumir,
                    4
                );

                $cantidadReservadaNueva = bcsub(
                    $cantidadReservadaAnterior,
                    $cantidadConsumir,
                    4
                );

                // ---------------------------------------------
                // ACTUALIZAR EXISTENCIA
                // Conserva el costo promedio y registra al
                // usuario responsable del consumo.
                // ---------------------------------------------

                $existencia->update([
                    'cantidad' => $cantidadNueva,
                    'cantidad_reservada' => $cantidadReservadaNueva,
                    'actualizado_por_id' => $actor->id,
                ]);

                // ---------------------------------------------
                // IDENTIFICADORES
                // Genera un UUID de grupo y un folio individual
                // para el movimiento de consumo.
                // ---------------------------------------------

                $grupoId = (string) Str::uuid();

                $folio = $this->generarFolio();

                // ---------------------------------------------
                // MOVIMIENTO INMUTABLE
                // Registra el consumo utilizando el costo
                // promedio vigente del producto.
                // ---------------------------------------------

                MovimientoInventario::query()->create([
                    'empresa_id' => $empresaBloqueada->id,
                    'almacen_id' => $almacenBloqueado->id,
                    'producto_id' => $productoBloqueado->id,
                    'grupo_id' => $grupoId,
                    'folio' => $folio,
                    'tipo' => MovimientoInventario::TIPO_CONSUMO,
                    'cantidad' => $cantidadConsumir,
                    'existencia_anterior' => $cantidadAnterior,
                    'existencia_nueva' => $cantidadNueva,
                    'costo_unitario' => $costoPromedio,
                    'referencia_tipo' => $datosNormalizados[
                            'referencia_tipo'
                        ],
                    'referencia_id' => $datosNormalizados[
                            'referencia_id'
                        ],
                    'motivo' => $motivo,
                    'usuario_id' => $actor->id,
                    'ocurrido_at' => now(),
                ]);

                // ---------------------------------------------
                // RECARGAR EXISTENCIA
                // Devuelve el saldo persistido con sus
                // relaciones principales cargadas.
                // ---------------------------------------------

                $existenciaActualizada =
                    $existencia->fresh();

                if ($existenciaActualizada === null) {
                    throw ValidationException::withMessages([
                        'existencia' => 'No fue posible recuperar la existencia actualizada.',
                    ]);
                }

                return $existenciaActualizada->load([
                    'empresa',
                    'almacen',
                    'producto',
                    'actualizadoPor',
                ]);
            },
            3
        );
    }

    // ---------------------------------------------------------
    // NORMALIZAR DATOS
    // Estandariza cantidad con cuatro decimales, limpia texto y
    // valida identificadores opcionales.
    // ---------------------------------------------------------

    /**
     * @param  array<string, mixed>  $datos
     * @return array{
     *     cantidad: string,
     *     motivo: string,
     *     referencia_tipo: string|null,
     *     referencia_id: int|null
     * }
     */
    private function normalizarDatos(
        array $datos
    ): array {
        $cantidad = $datos['cantidad'] ?? null;

        $motivo = $datos['motivo'] ?? '';

        $referenciaTipo =
            $datos['referencia_tipo'] ?? null;

        $referenciaId =
            $datos['referencia_id'] ?? null;

        return [
            'cantidad' => $this->normalizarDecimal(
                $cantidad
            ),
            'motivo' => is_string($motivo)
                ? trim($motivo)
                : '',
            'referencia_tipo' => is_string($referenciaTipo)
                    && trim($referenciaTipo) !== ''
                        ? trim($referenciaTipo)
                        : null,
            'referencia_id' => filter_var(
                $referenciaId,
                FILTER_VALIDATE_INT
            ) !== false
                    ? (int) $referenciaId
                    : null,
        ];
    }

    // ---------------------------------------------------------
    // NORMALIZAR DECIMAL
    // Acepta enteros, flotantes o cadenas numéricas con un
    // máximo de cuatro posiciones decimales.
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

        $valorNormalizado = trim(
            (string) $valor
        );

        if (
            $valorNormalizado === ''
            || preg_match(
                '/^-?\d+(?:\.\d{1,4})?$/',
                $valorNormalizado
            ) !== 1
        ) {
            return '';
        }

        return bcadd(
            $valorNormalizado,
            '0',
            4
        );
    }

    // ---------------------------------------------------------
    // VALIDAR OPERACIÓN
    // Comprueba autorización, pertenencia, estados operativos,
    // cantidad y motivo del consumo.
    // ---------------------------------------------------------

    /**
     * @param  array{
     *     cantidad: string,
     *     motivo: string,
     *     referencia_tipo: string|null,
     *     referencia_id: int|null
     * }  $datos
     */
    private function validar(
        Empresa $empresa,
        Almacen $almacen,
        Producto $producto,
        User $actor,
        array $datos
    ): void {
        // -----------------------------------------------------
        // PERMISO
        // Requiere inventario.consumir por rol o directo.
        // -----------------------------------------------------

        if (
            ! $actor->can(
                'inventario.consumir'
            )
        ) {
            throw new AuthorizationException(
                'No tienes permiso para consumir inventario reservado.'
            );
        }

        // -----------------------------------------------------
        // ALMACÉN DE LA EMPRESA
        // Evita consumir existencias de un almacén ajeno.
        // -----------------------------------------------------

        if ($almacen->empresa_id !== $empresa->id) {
            throw new AuthorizationException(
                'El almacén no pertenece a la empresa indicada.'
            );
        }

        // -----------------------------------------------------
        // PRODUCTO DE LA EMPRESA
        // Evita consumir existencias de un producto externo.
        // -----------------------------------------------------

        if ($producto->empresa_id !== $empresa->id) {
            throw new AuthorizationException(
                'El producto no pertenece a la empresa indicada.'
            );
        }

        // -----------------------------------------------------
        // EMPRESA ACTIVA
        // Una empresa inactiva no puede registrar consumos.
        // -----------------------------------------------------

        if (! $empresa->activo) {
            throw ValidationException::withMessages([
                'empresa' => 'No se puede consumir inventario de una empresa inactiva.',
            ]);
        }

        // -----------------------------------------------------
        // ALMACÉN ACTIVO
        // El almacén debe permanecer activo.
        // -----------------------------------------------------

        if (! $almacen->activo) {
            throw ValidationException::withMessages([
                'almacen' => 'No se puede consumir inventario de un almacén inactivo.',
            ]);
        }

        // -----------------------------------------------------
        // MANEJO DE EXISTENCIAS
        // Respeta la bandera de existencias del almacén.
        // -----------------------------------------------------

        if (! $almacen->permite_existencias) {
            throw ValidationException::withMessages([
                'almacen' => 'El almacén seleccionado no permite manejar existencias.',
            ]);
        }

        // -----------------------------------------------------
        // SUCURSAL DEL ALMACÉN FÍSICO
        // Valida que la sucursal física exista, pertenezca y esté activa.
        // -----------------------------------------------------

        if (! $almacen->es_virtual) {
            $sucursal = $almacen->sucursal;

            if ($sucursal === null) {
                throw ValidationException::withMessages([
                    'sucursal' => 'El almacén físico debe pertenecer a una sucursal.',
                ]);
            }

            if (
                $sucursal->empresa_id
                    !== $empresa->id
            ) {
                throw new AuthorizationException(
                    'La sucursal del almacén no pertenece a la empresa indicada.'
                );
            }

            if (! $sucursal->activo) {
                throw ValidationException::withMessages([
                    'sucursal' => 'No se puede consumir inventario dentro de una sucursal inactiva.',
                ]);
            }
        }

        // -----------------------------------------------------
        // PRODUCTO ACTIVO
        // No permite consumir productos desactivados.
        // -----------------------------------------------------

        if (! $producto->activo) {
            throw ValidationException::withMessages([
                'producto' => 'No se puede consumir inventario de un producto inactivo.',
            ]);
        }

        // -----------------------------------------------------
        // CANTIDAD POSITIVA
        // La cantidad a consumir debe ser mayor que cero con hasta 4 decimales.
        // -----------------------------------------------------

        if (
            $datos['cantidad'] === ''
            || bccomp(
                $datos['cantidad'],
                '0.0000',
                4
            ) !== 1
        ) {
            throw ValidationException::withMessages([
                'cantidad' => 'La cantidad a consumir debe ser mayor que cero y tener hasta cuatro decimales.',
            ]);
        }

        // -----------------------------------------------------
        // MOTIVO
        // Todo consumo requiere justificación de hasta 1000 caracteres.
        // -----------------------------------------------------

        if (
            $datos['motivo'] === ''
            || mb_strlen($datos['motivo']) > 1000
        ) {
            throw ValidationException::withMessages([
                'motivo' => 'Debes indicar un motivo válido de hasta 1000 caracteres.',
            ]);
        }
    }

    // ---------------------------------------------------------
    // GENERAR FOLIO
    // Genera un folio legible para el movimiento en bitácora.
    // ---------------------------------------------------------

    private function generarFolio(): string
    {
        return 'MOV-'
            .now()->format('YmdHisv')
            .'-'
            .Str::upper(
                Str::random(6)
            );
    }
}
