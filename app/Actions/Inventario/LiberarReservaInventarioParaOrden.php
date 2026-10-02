<?php

namespace App\Actions\Inventario;

use App\Models\Almacen;
use App\Models\Empresa;
use App\Models\OrdenServicio;
use App\Models\Producto;
use App\Models\ReservaInventario;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LiberarReservaInventarioParaOrden
{
    // ---------------------------------------------------------
    // DEPENDENCIA DE LIBERACIÓN CONSOLIDADA
    // Reutiliza las reglas existentes para disminuir la
    // cantidad reservada en existencias_almacen.
    // ---------------------------------------------------------

    public function __construct(
        private LiberarReservaInventario $liberarReservaInventario
    ) {}

    // ---------------------------------------------------------
    // LIBERAR RESERVA POR ORDEN
    // Sincroniza la existencia consolidada y el detalle formal
    // asociado con una orden de servicio.
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
        // Prepara cantidad y motivo para actualizar el detalle
        // formal con los mismos valores de la operación base.
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
        // La existencia consolidada y la reserva formal deben
        // actualizarse juntas o revertirse completamente.
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
                // antes de modificar la reserva relacionada.
                // ---------------------------------------------

                $ordenBloqueada = OrdenServicio::query()
                    ->with('equipo')
                    ->lockForUpdate()
                    ->findOrFail($orden->id);

                // ---------------------------------------------
                // INTEGRIDAD DE LA ORDEN
                // Confirma que el equipo pertenezca al cliente
                // titular de la orden.
                // ---------------------------------------------

                $this->validarOrden(
                    $ordenBloqueada
                );

                // ---------------------------------------------
                // RESERVA FORMAL BLOQUEADA
                // Localiza el registro exacto de la orden, el
                // almacén y el producto.
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
                // Una reserva finalizada no conserva unidades
                // pendientes que puedan liberarse nuevamente.
                // ---------------------------------------------

                if (! $reserva->estaActiva()) {
                    throw ValidationException::withMessages([
                        'reserva' => 'La reserva de inventario ya está finalizada.',
                    ]);
                }

                // ---------------------------------------------
                // CANTIDAD PENDIENTE
                // La liberación solo puede utilizar unidades
                // pendientes de la reserva específica.
                // ---------------------------------------------

                $cantidadPendiente = $reserva
                    ->cantidadPendiente();

                if (
                    $cantidad === ''
                    || bccomp(
                        $cantidad,
                        '0.0000',
                        4
                    ) !== 1
                ) {
                    throw ValidationException::withMessages([
                        'cantidad' => 'La cantidad a liberar debe ser mayor que cero y tener hasta cuatro decimales.',
                    ]);
                }

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
                // LIBERACIÓN CONSOLIDADA
                // Disminuye cantidad_reservada dentro de
                // existencias_almacen utilizando la acción base.
                // ---------------------------------------------

                $this->liberarReservaInventario->ejecutar(
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
                // ACUMULAR CANTIDAD LIBERADA
                // Conserva la cantidad reservada histórica y
                // aumenta solamente la cantidad liberada.
                // ---------------------------------------------

                $cantidadLiberadaNueva = bcadd(
                    (string) $reserva->cantidad_liberada,
                    $cantidad,
                    4
                );

                $cantidadFinalizada = bcadd(
                    (string) $reserva->cantidad_consumida,
                    $cantidadLiberadaNueva,
                    4
                );

                $quedoFinalizada = bccomp(
                    $cantidadFinalizada,
                    (string) $reserva->cantidad_reservada,
                    4
                ) === 0;

                // ---------------------------------------------
                // ESTADO DE LA RESERVA
                // Una liberación completa finaliza la reserva.
                // Una liberación parcial mantiene saldo activo.
                // ---------------------------------------------

                $estado = $quedoFinalizada
                    ? ReservaInventario::ESTADO_LIBERADA
                    : ReservaInventario::ESTADO_PARCIAL;

                $reserva->update([
                    'cantidad_liberada' => $cantidadLiberadaNueva,
                    'estado' => $estado,
                    'motivo' => $motivoNormalizado,
                    'actualizado_por_id' => $actor->id,
                    'finalizado_at' => $quedoFinalizada
                        ? now()
                        : null,
                ]);

                // ---------------------------------------------
                // RECARGAR LA RESERVA
                // Devuelve los datos persistidos junto con sus
                // relaciones principales.
                // ---------------------------------------------

                $reservaActualizada =
                    $reserva->fresh();

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
    // Comprueba que la orden conserve un equipo perteneciente
    // al mismo cliente registrado como titular.
    // ---------------------------------------------------------

    private function validarOrden(
        OrdenServicio $orden
    ): void {
        $equipo = $orden->equipo;

        if ($equipo === null) {
            throw ValidationException::withMessages([
                'orden' => 'La orden debe tener un equipo relacionado.',
            ]);
        }

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
    // Convierte la cantidad a una cadena decimal uniforme de
    // cuatro posiciones.
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
