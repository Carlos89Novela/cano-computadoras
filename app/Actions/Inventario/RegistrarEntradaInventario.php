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

class RegistrarEntradaInventario
{
    // ---------------------------------------------------------
    // REGISTRAR ENTRADA
    // Incrementa la existencia de un producto dentro de un
    // almacén y registra el movimiento correspondiente.
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
        // Estandariza cantidad, costo, motivo y referencias
        // opcionales antes de realizar las validaciones.
        // -----------------------------------------------------

        $datosNormalizados = $this->normalizarDatos(
            $datos
        );

        // -----------------------------------------------------
        // VALIDACIÓN PRELIMINAR
        // Rechaza la operación antes de iniciar la transacción
        // cuando alguna regla básica no se cumple.
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
        // El saldo y el movimiento deben persistirse juntos.
        // Si cualquier operación falla, ambas se revierten.
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
                // Relee y bloquea empresa, almacén y producto
                // para evitar cambios concurrentes de estado.
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
                // Confirma las reglas sobre los registros
                // obtenidos bajo bloqueo.
                // ---------------------------------------------

                $this->validar(
                    empresa: $empresaBloqueada,
                    almacen: $almacenBloqueado,
                    producto: $productoBloqueado,
                    actor: $actor,
                    datos: $datosNormalizados
                );

                $cantidadEntrada = (string) (
                    $datosNormalizados['cantidad']
                );

                $costoEntrada = (string) (
                    $datosNormalizados['costo_unitario']
                );

                $motivo = (string) (
                    $datosNormalizados['motivo']
                );

                // ---------------------------------------------
                // EXISTENCIA ACTUAL
                // Busca y bloquea el saldo del producto dentro
                // del almacén para evitar actualizaciones
                // simultáneas sobre el mismo registro.
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

                // ---------------------------------------------
                // SALDO ANTERIOR
                // Si todavía no existe registro, la cantidad y
                // el costo promedio comienzan en cero.
                // ---------------------------------------------

                $cantidadAnterior = $existencia !== null
                    ? (string) $existencia->cantidad
                    : '0.0000';

                $costoPromedioAnterior =
                    $existencia !== null
                        ? (string) $existencia
                            ->costo_promedio
                        : '0.0000';

                // ---------------------------------------------
                // NUEVA CANTIDAD
                // La entrada siempre suma unidades al saldo
                // existente del producto.
                // ---------------------------------------------

                $cantidadNueva = bcadd(
                    $cantidadAnterior,
                    $cantidadEntrada,
                    4
                );

                // ---------------------------------------------
                // COSTO PROMEDIO PONDERADO
                // ((cantidad anterior × costo anterior)
                // + (cantidad entrada × costo entrada))
                // ÷ cantidad nueva.
                // ---------------------------------------------

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

                // ---------------------------------------------
                // CREAR EXISTENCIA
                // Cuando el producto todavía no tiene saldo en
                // el almacén, crea su registro inicial.
                // ---------------------------------------------

                if ($existencia === null) {
                    $existencia = ExistenciaAlmacen::query()
                        ->create([
                            'empresa_id' => $empresaBloqueada->id,
                            'almacen_id' => $almacenBloqueado->id,
                            'producto_id' => $productoBloqueado->id,
                            'cantidad' => $cantidadNueva,
                            'cantidad_reservada' => '0.0000',
                            'costo_promedio' => $costoPromedioNuevo,
                            'actualizado_por_id' => $actor->id,
                        ]);
                } else {
                    // -----------------------------------------
                    // ACTUALIZAR EXISTENCIA
                    // Conserva las unidades reservadas y cambia
                    // solamente cantidad, costo y responsable.
                    // -----------------------------------------

                    $existencia->update([
                        'cantidad' => $cantidadNueva,
                        'costo_promedio' => $costoPromedioNuevo,
                        'actualizado_por_id' => $actor->id,
                    ]);
                }

                // ---------------------------------------------
                // IDENTIFICADORES DEL MOVIMIENTO
                // El grupo relaciona movimientos compuestos y
                // el folio identifica este movimiento.
                // ---------------------------------------------

                $grupoId = (string) Str::uuid();

                $folio = $this->generarFolio();

                // ---------------------------------------------
                // MOVIMIENTO INMUTABLE
                // Registra la entrada con el saldo anterior,
                // el nuevo saldo y su costo unitario.
                // ---------------------------------------------

                MovimientoInventario::query()->create([
                    'empresa_id' => $empresaBloqueada->id,
                    'almacen_id' => $almacenBloqueado->id,
                    'producto_id' => $productoBloqueado->id,
                    'grupo_id' => $grupoId,
                    'folio' => $folio,
                    'tipo' => MovimientoInventario::TIPO_ENTRADA,
                    'cantidad' => $cantidadEntrada,
                    'existencia_anterior' => $cantidadAnterior,
                    'existencia_nueva' => $cantidadNueva,
                    'costo_unitario' => $costoEntrada,
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
    // Convierte cantidades válidas a cadenas con cuatro
    // decimales y limpia el motivo y las referencias.
    // ---------------------------------------------------------

    /**
     * @param  array<string, mixed>  $datos
     * @return array{
     *     cantidad: string,
     *     costo_unitario: string,
     *     motivo: string,
     *     referencia_tipo: string|null,
     *     referencia_id: int|null
     * }
     */
    private function normalizarDatos(
        array $datos
    ): array {
        $cantidad = $datos['cantidad'] ?? null;

        $costoUnitario =
            $datos['costo_unitario'] ?? null;

        $motivo = $datos['motivo'] ?? '';

        $referenciaTipo =
            $datos['referencia_tipo'] ?? null;

        $referenciaId =
            $datos['referencia_id'] ?? null;

        return [
            'cantidad' => $this->normalizarDecimal(
                $cantidad
            ),
            'costo_unitario' => $this->normalizarDecimal(
                $costoUnitario
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
    // Acepta enteros, flotantes o cadenas numéricas y devuelve
    // una representación uniforme con cuatro decimales.
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
    // Comprueba permisos, pertenencia, estados activos,
    // capacidad de inventario y datos monetarios.
    // ---------------------------------------------------------

    /**
     * @param  array{
     *     cantidad: string,
     *     costo_unitario: string,
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
        // El actor necesita inventario.registrar_entrada por
        // rol o por asignación directa.
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
        // Impide registrar una entrada utilizando un almacén
        // perteneciente a otra empresa.
        // -----------------------------------------------------

        if ($almacen->empresa_id !== $empresa->id) {
            throw new AuthorizationException(
                'El almacén no pertenece a la empresa indicada.'
            );
        }

        // -----------------------------------------------------
        // PRODUCTO DE LA EMPRESA
        // Impide afectar existencias de un producto que
        // pertenece a otra empresa.
        // -----------------------------------------------------

        if ($producto->empresa_id !== $empresa->id) {
            throw new AuthorizationException(
                'El producto no pertenece a la empresa indicada.'
            );
        }

        // -----------------------------------------------------
        // EMPRESA ACTIVA
        // Una empresa desactivada no puede registrar nuevos
        // movimientos de inventario.
        // -----------------------------------------------------

        if (! $empresa->activo) {
            throw ValidationException::withMessages([
                'empresa' => 'No se pueden registrar entradas para una empresa inactiva.',
            ]);
        }

        // -----------------------------------------------------
        // ALMACÉN ACTIVO
        // El almacén debe estar operativo para recibir nuevas
        // unidades.
        // -----------------------------------------------------

        if (! $almacen->activo) {
            throw ValidationException::withMessages([
                'almacen' => 'No se pueden registrar entradas en un almacén inactivo.',
            ]);
        }

        // -----------------------------------------------------
        // ALMACÉN CON EXISTENCIAS
        // Respeta la configuración que determina si el almacén
        // puede manejar saldos cuantificables.
        // -----------------------------------------------------

        if (! $almacen->permite_existencias) {
            throw ValidationException::withMessages([
                'almacen' => 'El almacén seleccionado no permite manejar existencias.',
            ]);
        }

        // -----------------------------------------------------
        // SUCURSAL DEL ALMACÉN FÍSICO
        // Los almacenes físicos deben pertenecer a una
        // sucursal activa de la misma empresa.
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
                    'sucursal' => 'No se pueden registrar entradas en una sucursal inactiva.',
                ]);
            }
        }

        // -----------------------------------------------------
        // PRODUCTO ACTIVO
        // No se reciben unidades de productos desactivados.
        // -----------------------------------------------------

        if (! $producto->activo) {
            throw ValidationException::withMessages([
                'producto' => 'No se pueden registrar entradas para un producto inactivo.',
            ]);
        }

        // -----------------------------------------------------
        // CANTIDAD POSITIVA
        // Toda entrada debe incrementar el saldo con una
        // cantidad mayor que cero.
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
                'cantidad' => 'La cantidad de entrada debe ser mayor que cero y tener hasta cuatro decimales.',
            ]);
        }

        // -----------------------------------------------------
        // COSTO NO NEGATIVO
        // El costo puede ser cero para entradas especiales,
        // pero nunca puede ser negativo.
        // -----------------------------------------------------

        if (
            $datos['costo_unitario'] === ''
            || bccomp(
                $datos['costo_unitario'],
                '0.0000',
                4
            ) === -1
        ) {
            throw ValidationException::withMessages([
                'costo_unitario' => 'El costo unitario debe ser igual o mayor que cero y tener hasta cuatro decimales.',
            ]);
        }

        // -----------------------------------------------------
        // MOTIVO
        // Toda entrada debe incluir una justificación válida
        // para conservar la trazabilidad operativa.
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
    // Genera un identificador legible y suficientemente único
    // para el movimiento dentro de la empresa.
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
