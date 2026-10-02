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

class RegistrarSalidaInventario
{
    // ---------------------------------------------------------
    // REGISTRAR SALIDA
    // Descuenta unidades disponibles de un producto dentro
    // de un almacén y registra el movimiento correspondiente.
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
        // validar la operación de salida.
        // -----------------------------------------------------

        $datosNormalizados = $this->normalizarDatos(
            $datos
        );

        // -----------------------------------------------------
        // VALIDACIÓN PRELIMINAR
        // Comprueba permisos, pertenencia empresarial, estados
        // y formato de los datos antes de la transacción.
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
        // movimiento deben completarse juntas.
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
                // Impide cambios concurrentes en la empresa,
                // almacén y producto durante la salida.
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
                // los registros obtenidos bajo bloqueo.
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
                // Localiza el saldo específico del producto
                // dentro del almacén y evita salidas paralelas.
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

                $cantidadSalida = (string) (
                    $datosNormalizados['cantidad']
                );

                $cantidadAnterior = (string) (
                    $existencia->cantidad
                );

                $cantidadReservada = (string) (
                    $existencia->cantidad_reservada
                );

                $costoPromedio = (string) (
                    $existencia->costo_promedio
                );

                $motivo = (string) (
                    $datosNormalizados['motivo']
                );

                // ---------------------------------------------
                // CANTIDAD DISPONIBLE
                // Las unidades reservadas permanecen dentro del
                // saldo físico, pero no pueden consumirse.
                // ---------------------------------------------

                $cantidadDisponible = bcsub(
                    $cantidadAnterior,
                    $cantidadReservada,
                    4
                );

                if (
                    bccomp(
                        $cantidadSalida,
                        $cantidadDisponible,
                        4
                    ) === 1
                ) {
                    throw ValidationException::withMessages([
                        'cantidad' => 'La cantidad solicitada supera la existencia disponible.',
                    ]);
                }

                // ---------------------------------------------
                // NUEVA EXISTENCIA
                // Descuenta la salida del saldo físico total,
                // conservando intacta la cantidad reservada.
                // ---------------------------------------------

                $cantidadNueva = bcsub(
                    $cantidadAnterior,
                    $cantidadSalida,
                    4
                );

                // ---------------------------------------------
                // ACTUALIZAR EXISTENCIA
                // Mantiene el costo promedio y las reservas,
                // modificando solo cantidad y responsable.
                // ---------------------------------------------

                $existencia->update([
                    'cantidad' => $cantidadNueva,
                    'actualizado_por_id' => $actor->id,
                ]);

                // ---------------------------------------------
                // IDENTIFICADORES
                // Genera un UUID para agrupar operaciones y un
                // folio legible para este movimiento.
                // ---------------------------------------------

                $grupoId = (string) Str::uuid();

                $folio = $this->generarFolio();

                // ---------------------------------------------
                // MOVIMIENTO INMUTABLE
                // Registra la salida con el saldo anterior, el
                // nuevo saldo y el costo promedio vigente.
                // ---------------------------------------------

                MovimientoInventario::query()->create([
                    'empresa_id' => $empresaBloqueada->id,
                    'almacen_id' => $almacenBloqueado->id,
                    'producto_id' => $productoBloqueado->id,
                    'grupo_id' => $grupoId,
                    'folio' => $folio,
                    'tipo' => MovimientoInventario::TIPO_SALIDA,
                    'cantidad' => $cantidadSalida,
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
    // Convierte la cantidad a cuatro decimales, limpia el
    // motivo y prepara las referencias opcionales.
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
    // Comprueba permiso, pertenencia, estados y datos básicos
    // antes de localizar y modificar la existencia.
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
        // El actor necesita inventario.registrar_salida por
        // rol o por asignación directa.
        // -----------------------------------------------------

        if (
            ! $actor->can(
                'inventario.registrar_salida'
            )
        ) {
            throw new AuthorizationException(
                'No tienes permiso para registrar salidas de inventario.'
            );
        }

        // -----------------------------------------------------
        // ALMACÉN DE LA EMPRESA
        // Impide usar un almacén perteneciente a otra empresa.
        // -----------------------------------------------------

        if ($almacen->empresa_id !== $empresa->id) {
            throw new AuthorizationException(
                'El almacén no pertenece a la empresa indicada.'
            );
        }

        // -----------------------------------------------------
        // PRODUCTO DE LA EMPRESA
        // Impide retirar existencias de un producto externo.
        // -----------------------------------------------------

        if ($producto->empresa_id !== $empresa->id) {
            throw new AuthorizationException(
                'El producto no pertenece a la empresa indicada.'
            );
        }

        // -----------------------------------------------------
        // EMPRESA ACTIVA
        // Una empresa inactiva no puede registrar movimientos
        // nuevos de inventario.
        // -----------------------------------------------------

        if (! $empresa->activo) {
            throw ValidationException::withMessages([
                'empresa' => 'No se pueden registrar salidas para una empresa inactiva.',
            ]);
        }

        // -----------------------------------------------------
        // ALMACÉN ACTIVO
        // El almacén debe permanecer operativo para entregar
        // productos de su inventario.
        // -----------------------------------------------------

        if (! $almacen->activo) {
            throw ValidationException::withMessages([
                'almacen' => 'No se pueden registrar salidas de un almacén inactivo.',
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
                    'sucursal' => 'No se pueden registrar salidas en una sucursal inactiva.',
                ]);
            }
        }

        // -----------------------------------------------------
        // PRODUCTO ACTIVO
        // No permite retirar mediante esta operación productos
        // que están desactivados.
        // -----------------------------------------------------

        if (! $producto->activo) {
            throw ValidationException::withMessages([
                'producto' => 'No se pueden registrar salidas para un producto inactivo.',
            ]);
        }

        // -----------------------------------------------------
        // CANTIDAD POSITIVA
        // La salida debe utilizar una cantidad mayor que cero
        // y con un máximo de cuatro decimales.
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
                'cantidad' => 'La cantidad de salida debe ser mayor que cero y tener hasta cuatro decimales.',
            ]);
        }

        // -----------------------------------------------------
        // MOTIVO
        // Toda salida debe conservar una justificación de
        // hasta 1000 caracteres.
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
    // Genera un identificador legible para el movimiento de
    // salida dentro de la bitácora de inventario.
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
