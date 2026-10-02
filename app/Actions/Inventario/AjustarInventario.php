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

class AjustarInventario
{
    // ---------------------------------------------------------
    // AJUSTAR INVENTARIO
    // Establece una cantidad física objetivo y registra como
    // movimiento únicamente la diferencia detectada.
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
        // Estandariza la cantidad objetivo, el motivo y las
        // referencias opcionales de la operación.
        // -----------------------------------------------------

        $datosNormalizados = $this->normalizarDatos(
            $datos
        );

        // -----------------------------------------------------
        // VALIDACIÓN PRELIMINAR
        // Comprueba autorización, pertenencia, estados y datos
        // antes de iniciar la transacción.
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
        // La actualización del saldo y la creación del
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
                // Evita cambios concurrentes de estado mientras
                // se procesa el ajuste de inventario.
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
                // Confirma nuevamente las reglas utilizando
                // los registros recuperados bajo bloqueo.
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
                // Localiza el saldo exacto del producto dentro
                // del almacén y evita ajustes simultáneos.
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

                $cantidadAnterior = (string) (
                    $existencia->cantidad
                );

                $cantidadReservada = (string) (
                    $existencia->cantidad_reservada
                );

                $costoPromedio = (string) (
                    $existencia->costo_promedio
                );

                $cantidadObjetivo = (string) (
                    $datosNormalizados[
                        'cantidad_objetivo'
                    ]
                );

                $motivo = (string) (
                    $datosNormalizados['motivo']
                );

                // ---------------------------------------------
                // CAMBIO REAL
                // Impide registrar un movimiento sin diferencia
                // entre el saldo actual y el conteo físico.
                // ---------------------------------------------

                if (
                    bccomp(
                        $cantidadObjetivo,
                        $cantidadAnterior,
                        4
                    ) === 0
                ) {
                    throw ValidationException::withMessages([
                        'cantidad_objetivo' => 'La cantidad objetivo debe ser diferente de la existencia actual.',
                    ]);
                }

                // ---------------------------------------------
                // PROTECCIÓN DE RESERVAS
                // El ajuste no puede colocar la existencia
                // física por debajo de las unidades reservadas.
                // ---------------------------------------------

                if (
                    bccomp(
                        $cantidadObjetivo,
                        $cantidadReservada,
                        4
                    ) === -1
                ) {
                    throw ValidationException::withMessages([
                        'cantidad_objetivo' => 'La cantidad objetivo no puede ser menor que la cantidad reservada.',
                    ]);
                }

                // ---------------------------------------------
                // DIFERENCIA Y TIPO
                // Determina automáticamente si el ajuste suma o
                // resta unidades del saldo existente.
                // ---------------------------------------------

                $comparacion = bccomp(
                    $cantidadObjetivo,
                    $cantidadAnterior,
                    4
                );

                if ($comparacion === 1) {
                    $tipoMovimiento =
                        MovimientoInventario::TIPO_AJUSTE_POSITIVO;

                    $diferencia = bcsub(
                        $cantidadObjetivo,
                        $cantidadAnterior,
                        4
                    );
                } else {
                    $tipoMovimiento =
                        MovimientoInventario::TIPO_AJUSTE_NEGATIVO;

                    $diferencia = bcsub(
                        $cantidadAnterior,
                        $cantidadObjetivo,
                        4
                    );
                }

                // ---------------------------------------------
                // ACTUALIZAR EXISTENCIA
                // Establece la cantidad física objetivo y
                // conserva reservas y costo promedio.
                // ---------------------------------------------

                $existencia->update([
                    'cantidad' => $cantidadObjetivo,
                    'actualizado_por_id' => $actor->id,
                ]);

                // ---------------------------------------------
                // IDENTIFICADORES
                // Genera un grupo UUID y un folio individual
                // para el movimiento de ajuste.
                // ---------------------------------------------

                $grupoId = (string) Str::uuid();

                $folio = $this->generarFolio();

                // ---------------------------------------------
                // MOVIMIENTO INMUTABLE
                // Registra la diferencia detectada, los saldos
                // anterior y nuevo y el costo promedio vigente.
                // ---------------------------------------------

                MovimientoInventario::query()->create([
                    'empresa_id' => $empresaBloqueada->id,
                    'almacen_id' => $almacenBloqueado->id,
                    'producto_id' => $productoBloqueado->id,
                    'grupo_id' => $grupoId,
                    'folio' => $folio,
                    'tipo' => $tipoMovimiento,
                    'cantidad' => $diferencia,
                    'existencia_anterior' => $cantidadAnterior,
                    'existencia_nueva' => $cantidadObjetivo,
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
                // Devuelve los valores persistidos junto con
                // sus relaciones principales.
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
    // Convierte la cantidad objetivo a cuatro decimales,
    // limpia el motivo y prepara referencias opcionales.
    // ---------------------------------------------------------

    /**
     * @param  array<string, mixed>  $datos
     * @return array{
     *     cantidad_objetivo: string,
     *     motivo: string,
     *     referencia_tipo: string|null,
     *     referencia_id: int|null
     * }
     */
    private function normalizarDatos(
        array $datos
    ): array {
        $cantidadObjetivo =
            $datos['cantidad_objetivo'] ?? null;

        $motivo = $datos['motivo'] ?? '';

        $referenciaTipo =
            $datos['referencia_tipo'] ?? null;

        $referenciaId =
            $datos['referencia_id'] ?? null;

        return [
            'cantidad_objetivo' => $this->normalizarDecimal(
                $cantidadObjetivo
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
    // Comprueba permiso, pertenencia, estados operativos,
    // cantidad objetivo y motivo de la operación.
    // ---------------------------------------------------------

    /**
     * @param  array{
     *     cantidad_objetivo: string,
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
        // El actor necesita inventario.ajustar mediante su rol
        // o mediante una asignación individual.
        // -----------------------------------------------------

        if (! $actor->can('inventario.ajustar')) {
            throw new AuthorizationException(
                'No tienes permiso para ajustar existencias.'
            );
        }

        // -----------------------------------------------------
        // ALMACÉN DE LA EMPRESA
        // Impide ajustar saldos mediante un almacén externo.
        // -----------------------------------------------------

        if ($almacen->empresa_id !== $empresa->id) {
            throw new AuthorizationException(
                'El almacén no pertenece a la empresa indicada.'
            );
        }

        // -----------------------------------------------------
        // PRODUCTO DE LA EMPRESA
        // Impide ajustar un producto perteneciente a otra
        // empresa.
        // -----------------------------------------------------

        if ($producto->empresa_id !== $empresa->id) {
            throw new AuthorizationException(
                'El producto no pertenece a la empresa indicada.'
            );
        }

        // -----------------------------------------------------
        // EMPRESA ACTIVA
        // Una empresa inactiva no puede registrar ajustes.
        // -----------------------------------------------------

        if (! $empresa->activo) {
            throw ValidationException::withMessages([
                'empresa' => 'No se pueden ajustar existencias de una empresa inactiva.',
            ]);
        }

        // -----------------------------------------------------
        // ALMACÉN ACTIVO
        // El almacén debe estar operativo para realizar un
        // conteo y ajustar sus existencias.
        // -----------------------------------------------------

        if (! $almacen->activo) {
            throw ValidationException::withMessages([
                'almacen' => 'No se pueden ajustar existencias de un almacén inactivo.',
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
                    'sucursal' => 'No se pueden ajustar existencias dentro de una sucursal inactiva.',
                ]);
            }
        }

        // -----------------------------------------------------
        // PRODUCTO ACTIVO
        // Los productos desactivados no pueden ajustarse
        // mediante la operación ordinaria de inventario.
        // -----------------------------------------------------

        if (! $producto->activo) {
            throw ValidationException::withMessages([
                'producto' => 'No se pueden ajustar existencias de un producto inactivo.',
            ]);
        }

        // -----------------------------------------------------
        // CANTIDAD OBJETIVO
        // Debe ser numérica, no negativa y utilizar hasta
        // cuatro decimales.
        // -----------------------------------------------------

        if (
            $datos['cantidad_objetivo'] === ''
            || bccomp(
                $datos['cantidad_objetivo'],
                '0.0000',
                4
            ) === -1
        ) {
            throw ValidationException::withMessages([
                'cantidad_objetivo' => 'La cantidad objetivo debe ser igual o mayor que cero y tener hasta cuatro decimales.',
            ]);
        }

        // -----------------------------------------------------
        // MOTIVO
        // Todo ajuste necesita una justificación de hasta
        // 1000 caracteres.
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
    // Crea un identificador legible para el movimiento de
    // ajuste dentro del kardex.
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
