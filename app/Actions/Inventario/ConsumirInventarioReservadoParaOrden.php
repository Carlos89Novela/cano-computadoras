<?php

namespace App\Actions\Inventario;

use App\Enums\EstadoOrden;
use App\Models\Almacen;
use App\Models\Empresa;
use App\Models\OrdenServicio;
use App\Models\Producto;
use App\Models\ReservaInventario;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConsumirInventarioReservadoParaOrden
{
    // ---------------------------------------------------------
    // DEPENDENCIA DE CONSUMO CONSOLIDADO
    // Reutiliza la acción que descuenta simultáneamente la
    // existencia física y la cantidad reservada.
    // ---------------------------------------------------------

    public function __construct(
        private ConsumirInventarioReservado $consumirInventarioReservado
    ) {}

    // ---------------------------------------------------------
    // CONSUMIR RESERVA POR ORDEN
    // Sincroniza el inventario consolidado y el detalle formal
    // de la reserva asociada con una orden de servicio.
    // ---------------------------------------------------------

    /**
     * @param  array<string, mixed>  $datos
     */
    public function ejecutar(
        Empresa $empresa,
        Almacen $almacen,
        Producto $producto,
        OrdenServicio $orden,
        User $actor,
        array $datos
    ): ReservaInventario {
        // -----------------------------------------------------
        // NORMALIZACIÓN
        // Prepara la cantidad y el motivo antes de iniciar la
        // operación transaccional.
        // -----------------------------------------------------

        $cantidad = $this->normalizarCantidad(
            $datos['cantidad'] ?? null
        );

        $motivo = $datos['motivo'] ?? '';

        $motivoNormalizado = is_string($motivo)
            ? trim($motivo)
            : '';

        // -----------------------------------------------------
        // TRANSACCIÓN GENERAL
        // El saldo consolidado, el movimiento y la reserva
        // formal deben cambiar juntos o revertirse por completo.
        // -----------------------------------------------------

        return DB::transaction(
            function () use (
                $empresa,
                $almacen,
                $producto,
                $orden,
                $actor,
                $datos,
                $cantidad,
                $motivoNormalizado
            ): ReservaInventario {
                // ---------------------------------------------
                // ORDEN BLOQUEADA
                // Recupera la orden y su equipo bajo bloqueo
                // antes de modificar el inventario relacionado.
                // ---------------------------------------------

                $ordenBloqueada = OrdenServicio::query()
                    ->with('equipo')
                    ->lockForUpdate()
                    ->findOrFail($orden->id);

                // ---------------------------------------------
                // VALIDACIÓN DE LA ORDEN
                // Impide consumos para órdenes finalizadas y
                // comprueba la propiedad del equipo.
                // ---------------------------------------------

                $this->validarOrden(
                    $ordenBloqueada
                );

                // ---------------------------------------------
                // RESERVA FORMAL BLOQUEADA
                // Localiza el detalle exacto correspondiente a
                // la orden, almacén y producto.
                // ---------------------------------------------

                $reserva = ReservaInventario::query()
                    ->where(
                        'empresa_id',
                        $empresa->id
                    )
                    ->where(
                        'almacen_id',
                        $almacen->id
                    )
                    ->where(
                        'producto_id',
                        $producto->id
                    )
                    ->where(
                        'orden_servicio_id',
                        $ordenBloqueada->id
                    )
                    ->lockForUpdate()
                    ->first();

                if ($reserva === null) {
                    throw ValidationException::withMessages([
                        'reserva' => 'La orden no tiene una reserva de inventario para el producto y almacén indicados.',
                    ]);
                }

                // ---------------------------------------------
                // RESERVA ACTIVA
                // Una reserva finalizada ya no conserva unidades
                // pendientes que puedan consumirse.
                // ---------------------------------------------

                if (! $reserva->estaActiva()) {
                    throw ValidationException::withMessages([
                        'reserva' => 'La reserva de inventario ya está finalizada.',
                    ]);
                }

                // ---------------------------------------------
                // CANTIDAD VÁLIDA
                // La cantidad debe ser positiva y tener como
                // máximo cuatro posiciones decimales.
                // ---------------------------------------------

                if (
                    $cantidad === ''
                    || bccomp(
                        $cantidad,
                        '0.0000',
                        4
                    ) !== 1
                ) {
                    throw ValidationException::withMessages([
                        'cantidad' => 'La cantidad a consumir debe ser mayor que cero y tener hasta cuatro decimales.',
                    ]);
                }

                // ---------------------------------------------
                // CANTIDAD PENDIENTE
                // El consumo solo puede utilizar unidades que
                // permanezcan pendientes en esta reserva.
                // ---------------------------------------------

                $cantidadPendiente = $reserva
                    ->cantidadPendiente();

                if (
                    bccomp(
                        $cantidad,
                        $cantidadPendiente,
                        4
                    ) === 1
                ) {
                    throw ValidationException::withMessages([
                        'cantidad' => 'La cantidad solicitada supera las unidades pendientes de la reserva.',
                    ]);
                }

                // ---------------------------------------------
                // CONSUMO CONSOLIDADO
                // Descuenta la existencia física y la reserva
                // consolidada, y registra el movimiento.
                // ---------------------------------------------

                $this->consumirInventarioReservado
                    ->ejecutar(
                        empresa: $empresa,
                        almacen: $almacen,
                        producto: $producto,
                        actor: $actor,
                        datos: [
                            'cantidad' => $datos['cantidad'] ?? null,
                            'referencia_tipo' => OrdenServicio::class,
                            'referencia_id' => $ordenBloqueada->id,
                            'motivo' => $motivoNormalizado,
                        ]
                    );

                // ---------------------------------------------
                // ACUMULAR CONSUMO
                // Conserva la cantidad reservada histórica y
                // aumenta el total consumido por la orden.
                // ---------------------------------------------

                $cantidadConsumidaNueva = bcadd(
                    (string) $reserva
                        ->cantidad_consumida,
                    $cantidad,
                    4
                );

                $cantidadFinalizada = bcadd(
                    $cantidadConsumidaNueva,
                    (string) $reserva
                        ->cantidad_liberada,
                    4
                );

                $quedoFinalizada = bccomp(
                    $cantidadFinalizada,
                    (string) $reserva
                        ->cantidad_reservada,
                    4
                ) === 0;

                // ---------------------------------------------
                // ESTADO RESULTANTE
                // Una reserva completamente procesada queda
                // consumida. En otro caso permanece parcial.
                // ---------------------------------------------

                $estado = $quedoFinalizada
                    ? ReservaInventario::ESTADO_CONSUMIDA
                    : ReservaInventario::ESTADO_PARCIAL;

                // ---------------------------------------------
                // ACTUALIZAR DETALLE FORMAL
                // Registra el consumo acumulado, el responsable
                // y la fecha de cierre cuando corresponda.
                // ---------------------------------------------

                $reserva->update([
                    'cantidad_consumida' => $cantidadConsumidaNueva,
                    'estado' => $estado,
                    'motivo' => $motivoNormalizado,
                    'actualizado_por_id' => $actor->id,
                    'finalizado_at' => $quedoFinalizada
                        ? now()
                        : null,
                ]);

                // ---------------------------------------------
                // RECARGAR RESERVA
                // Devuelve la reserva persistida junto con sus
                // relaciones principales.
                // ---------------------------------------------

                $reservaActualizada = $reserva->fresh();

                if ($reservaActualizada === null) {
                    throw ValidationException::withMessages([
                        'reserva' => 'No fue posible recuperar la reserva de inventario actualizada.',
                    ]);
                }

                return $reservaActualizada->load([
                    'empresa',
                    'almacen',
                    'producto',
                    'ordenServicio',
                    'reservadoPor',
                    'actualizadoPor',
                ]);
            },
            3
        );
    }

    // ---------------------------------------------------------
    // VALIDAR ORDEN
    // Rechaza órdenes terminales y comprueba que el equipo
    // pertenezca al cliente titular de la orden.
    // ---------------------------------------------------------

    private function validarOrden(
        OrdenServicio $orden
    ): void {
        // -----------------------------------------------------
        // ESTADO TERMINAL
        // Una orden entregada o cancelada no puede consumir
        // productos reservados mediante esta operación.
        // -----------------------------------------------------

        if (
            in_array(
                $orden->estado,
                EstadoOrden::finalizados(),
                true
            )
        ) {
            throw ValidationException::withMessages([
                'orden' => 'No se puede consumir inventario para una orden finalizada.',
            ]);
        }

        // -----------------------------------------------------
        // EQUIPO RELACIONADO
        // La orden debe conservar un equipo válido.
        // -----------------------------------------------------

        $equipo = $orden->equipo;

        if ($equipo === null) {
            throw ValidationException::withMessages([
                'orden' => 'La orden debe tener un equipo relacionado.',
            ]);
        }

        // -----------------------------------------------------
        // PROPIETARIO DEL EQUIPO
        // El equipo debe pertenecer al cliente que figura como
        // titular de la orden de servicio.
        // -----------------------------------------------------

        if (
            (int) $equipo->user_id
            !== (int) $orden->user_id
        ) {
            throw ValidationException::withMessages([
                'orden' => 'El equipo de la orden no pertenece al cliente indicado.',
            ]);
        }
    }

    // ---------------------------------------------------------
    // NORMALIZAR CANTIDAD
    // Convierte enteros, flotantes o cadenas numéricas a una
    // representación uniforme de cuatro decimales.
    // ---------------------------------------------------------

    private function normalizarCantidad(
        mixed $cantidad
    ): string {
        if (
            ! is_int($cantidad)
            && ! is_float($cantidad)
            && ! is_string($cantidad)
        ) {
            return '';
        }

        $cantidadNormalizada = trim(
            (string) $cantidad
        );

        if (
            $cantidadNormalizada === ''
            || preg_match(
                '/^-?\d+(?:\.\d{1,4})?$/',
                $cantidadNormalizada
            ) !== 1
        ) {
            return '';
        }

        return bcadd(
            $cantidadNormalizada,
            '0',
            4
        );
    }
}
