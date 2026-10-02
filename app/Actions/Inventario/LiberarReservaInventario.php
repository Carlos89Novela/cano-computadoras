<?php

namespace App\Actions\Inventario;

use App\Models\Almacen;
use App\Models\Empresa;
use App\Models\ExistenciaAlmacen;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LiberarReservaInventario
{
    // ---------------------------------------------------------
    // LIBERAR RESERVA
    // Reduce la cantidad reservada de un producto sin
    // modificar la existencia física ni el costo promedio.
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
        // Estandariza la cantidad, el motivo y las referencias
        // opcionales antes de validar la liberación.
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
        // La existencia se bloquea y actualiza dentro de una
        // operación atómica para evitar liberaciones paralelas.
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
                // bloqueo antes de modificar la reserva.
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
                // Localiza el saldo exacto y evita que otra
                // operación cambie la reserva simultáneamente.
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

                $cantidadLiberar = (string) (
                    $datosNormalizados['cantidad']
                );

                $cantidadReservadaActual = (string) (
                    $existencia->cantidad_reservada
                );

                // ---------------------------------------------
                // RESERVA SUFICIENTE
                // No permite liberar más unidades que las
                // actualmente reservadas.
                // ---------------------------------------------

                if (
                    bccomp(
                        $cantidadLiberar,
                        $cantidadReservadaActual,
                        4
                    ) === 1
                ) {
                    throw ValidationException::withMessages([
                        'cantidad' => 'La cantidad solicitada supera las unidades reservadas.',
                    ]);
                }

                // ---------------------------------------------
                // NUEVA CANTIDAD RESERVADA
                // Resta las unidades liberadas sin modificar
                // la existencia física.
                // ---------------------------------------------

                $cantidadReservadaNueva = bcsub(
                    $cantidadReservadaActual,
                    $cantidadLiberar,
                    4
                );

                // ---------------------------------------------
                // ACTUALIZAR EXISTENCIA
                // Conserva cantidad física y costo promedio;
                // modifica únicamente reserva y responsable.
                // ---------------------------------------------

                $existencia->update([
                    'cantidad_reservada' => $cantidadReservadaNueva,
                    'actualizado_por_id' => $actor->id,
                ]);

                // ---------------------------------------------
                // RECARGAR EXISTENCIA
                // Devuelve el registro persistido junto con sus
                // relaciones principales.
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
    // Convierte la cantidad a cuatro decimales y limpia el
    // motivo y las referencias opcionales.
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
    // cantidad y motivo de la liberación.
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
        // La liberación forma parte del flujo de consumo de
        // productos reservado para reparaciones.
        // -----------------------------------------------------

        if (! $actor->can('inventario.consumir')) {
            throw new AuthorizationException(
                'No tienes permiso para liberar reservas de inventario.'
            );
        }

        // -----------------------------------------------------
        // ALMACÉN DE LA EMPRESA
        // Evita liberar reservas de un almacén externo.
        // -----------------------------------------------------

        if ($almacen->empresa_id !== $empresa->id) {
            throw new AuthorizationException(
                'El almacén no pertenece a la empresa indicada.'
            );
        }

        // -----------------------------------------------------
        // PRODUCTO DE LA EMPRESA
        // Evita liberar reservas de un producto externo.
        // -----------------------------------------------------

        if ($producto->empresa_id !== $empresa->id) {
            throw new AuthorizationException(
                'El producto no pertenece a la empresa indicada.'
            );
        }

        // -----------------------------------------------------
        // EMPRESA ACTIVA
        // Una empresa inactiva no puede modificar reservas.
        // -----------------------------------------------------

        if (! $empresa->activo) {
            throw ValidationException::withMessages([
                'empresa' => 'No se pueden liberar reservas de una empresa inactiva.',
            ]);
        }

        // -----------------------------------------------------
        // ALMACÉN ACTIVO
        // El almacén debe estar operativo para modificar sus
        // unidades reservadas.
        // -----------------------------------------------------

        if (! $almacen->activo) {
            throw ValidationException::withMessages([
                'almacen' => 'No se pueden liberar reservas de un almacén inactivo.',
            ]);
        }

        // -----------------------------------------------------
        // MANEJO DE EXISTENCIAS
        // Respeta la configuración operativa del almacén.
        // -----------------------------------------------------

        if (! $almacen->permite_existencias) {
            throw ValidationException::withMessages([
                'almacen' => 'El almacén seleccionado no permite manejar existencias.',
            ]);
        }

        // -----------------------------------------------------
        // SUCURSAL DEL ALMACÉN FÍSICO
        // Todo almacén físico debe pertenecer a una sucursal
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
                    'sucursal' => 'No se pueden liberar reservas dentro de una sucursal inactiva.',
                ]);
            }
        }

        // -----------------------------------------------------
        // PRODUCTO ACTIVO
        // Un producto desactivado no puede modificar reservas
        // mediante la operación ordinaria.
        // -----------------------------------------------------

        if (! $producto->activo) {
            throw ValidationException::withMessages([
                'producto' => 'No se pueden liberar reservas de un producto inactivo.',
            ]);
        }

        // -----------------------------------------------------
        // CANTIDAD POSITIVA
        // Toda liberación debe ser mayor que cero y utilizar
        // un máximo de cuatro decimales.
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
                'cantidad' => 'La cantidad a liberar debe ser mayor que cero y tener hasta cuatro decimales.',
            ]);
        }

        // -----------------------------------------------------
        // MOTIVO
        // Toda liberación debe conservar una justificación
        // operativa de hasta 1000 caracteres.
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
}
