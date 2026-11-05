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

class ReservarInventarioParaOrden
{
    // ---------------------------------------------------------
    // DEPENDENCIA DE RESERVA CONSOLIDADA
    // Reutiliza las reglas ya probadas para actualizar la
    // cantidad reservada dentro de existencias_almacen.
    // ---------------------------------------------------------

    public function __construct(
        private ReservarInventario $reservarInventario
    ) {}

    // ---------------------------------------------------------
    // RESERVAR PARA UNA ORDEN
    // Actualiza el saldo consolidado y crea o incrementa el
    // detalle formal de la reserva asociado con la orden.
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
        // NORMALIZACIÓN DEL MOTIVO
        // El motivo se normaliza aqui para conservar el mismo
        // valor tanto en el saldo como en el detalle.
        // -----------------------------------------------------

        $motivo = $datos['motivo'] ?? '';

        $motivoNormalizado = is_string($motivo)
            ? trim($motivo)
            : '';

        // -----------------------------------------------------
        // VALIDACIÓN DE LA ORDEN
        // Confirma que la orden existe y que su equipo
        // pertenece al mismo cliente registrado en la orden.
        // -----------------------------------------------------

        $this->validarOrden(
            $orden
        );

        // -----------------------------------------------------
        // TRANSACCIÓN GENERAL
        // La existencia consolidada y el detalle por orden
        // deben actualizarse como una sola operación.
        // -----------------------------------------------------

        return DB::transaction(
            function () use (
                $empresa,
                $almacen,
                $producto,
                $orden,
                $actor,
                $datos,
                $motivoNormalizado
            ): ReservaInventario {
                // ---------------------------------------------
                // ORDEN BLOQUEADA
                // Evita cambios concurrentes en la orden
                // mientras se registra la reserva.
                // ---------------------------------------------

                $ordenBloqueada = OrdenServicio::query()
                    ->with('equipo')
                    ->lockForUpdate()
                    ->findOrFail($orden->id);

                // ---------------------------------------------
                // REVALIDACIÓN DE LA ORDEN
                // Confirma nuevamente la integridad de la orden
                // utilizando el registro bloqueado.
                // ---------------------------------------------

                $this->validarOrden(
                    $ordenBloqueada
                );

                // ---------------------------------------------
                // CANTIDAD NORMALIZADA
                // ReservarInventario valida y normaliza la
                // cantidad. Aquí se obtiene una representación
                // uniforme para actualizar el detalle.
                // ---------------------------------------------

                $cantidad = $this->normalizarCantidad(
                    $datos['cantidad'] ?? null
                );

                // ---------------------------------------------
                // RESERVA CONSOLIDADA
                // Actualiza existencias_almacen aplicando todas
                // las reglas de autorización, disponibilidad,
                // pertenencia y estados operativos existentes.
                // ---------------------------------------------

                $this->reservarInventario->ejecutar(
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
                // RESERVA FORMAL EXISTENTE
                // Busca y bloquea el registro acumulado de la
                // misma orden, almacén y producto.
                // ---------------------------------------------

                $reserva = ReservaInventario::query()
                    ->where(
                        'orden_servicio_id',
                        $ordenBloqueada->id
                    )
                    ->where(
                        'almacen_id',
                        $almacen->id
                    )
                    ->where(
                        'producto_id',
                        $producto->id
                    )
                    ->lockForUpdate()
                    ->first();

                // ---------------------------------------------
                // CREAR RESERVA FORMAL
                // Si todavía no existe detalle para esta
                // combinación, crea la reserva inicial.
                // ---------------------------------------------

                if ($reserva === null) {
                    $reserva = ReservaInventario::query()
                        ->create([
                            'empresa_id' => $empresa->id,
                            'almacen_id' => $almacen->id,
                            'producto_id' => $producto->id,
                            'orden_servicio_id' => $ordenBloqueada->id,
                            'cantidad_reservada' => $cantidad,
                            'cantidad_consumida' => '0.0000',
                            'cantidad_liberada' => '0.0000',
                            'estado' => ReservaInventario::ESTADO_ACTIVA,
                            'motivo' => $motivoNormalizado,
                            'reservado_por_id' => $actor->id,
                            'actualizado_por_id' => $actor->id,
                            'reservado_at' => now(),
                            'finalizado_at' => null,
                        ]);
                } else {
                    // -----------------------------------------
                    // AMPLIAR RESERVA FORMAL
                    // Acumula la nueva cantidad sobre la reserva
                    // existente sin duplicar el registro.
                    // -----------------------------------------

                    $cantidadReservadaNueva = bcadd(
                        (string) $reserva->cantidad_reservada,
                        $cantidad,
                        4
                    );

                    $reserva->update([
                        'cantidad_reservada' => $cantidadReservadaNueva,
                        'estado' => $this->determinarEstado(
                            $reserva
                        ),
                        'motivo' => $motivoNormalizado,
                        'actualizado_por_id' => $actor->id,
                        'finalizado_at' => null,
                    ]);
                }

                return $reserva;
            }
        );
    }

    // ---------------------------------------------------------
    // VALIDACIÓN DE LA ORDEN
    // Confirma que el equipo pertenezca al cliente de la orden.
    // ---------------------------------------------------------

    private function validarOrden(
        OrdenServicio $orden
    ): void {
        // -----------------------------------------------------
        // ESTADO TERMINAL
        // Las órdenes entregadas o canceladas no pueden recibir
        // nuevas reservas de inventario.
        // -----------------------------------------------------

        if (
            in_array(
                $orden->estado,
                EstadoOrden::finalizados(),
                true
            )
        ) {
            throw ValidationException::withMessages([
                'orden' => 'No se puede reservar inventario para una orden finalizada.',
            ]);
        }

        // -----------------------------------------------------
        // EQUIPO RELACIONADO
        // Toda orden debe conservar un equipo relacionado para
        // poder comprometer productos de inventario.
        // -----------------------------------------------------

        $equipo = $orden->equipo;

        if ($equipo === null) {
            throw ValidationException::withMessages([
                'orden' => 'La orden debe tener un equipo relacionado.',
            ]);
        }

        // -----------------------------------------------------
        // PROPIETARIO DEL EQUIPO
        // El equipo debe pertenecer al mismo cliente registrado
        // como titular de la orden de servicio.
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
    // Estandariza la cantidad a cuatro decimales para el detalle.
    // ---------------------------------------------------------

    private function normalizarCantidad(
        mixed $valor
    ): string {
        if (
            ! is_int($valor)
            && ! is_float($valor)
            && ! is_string($valor)
        ) {
            return '0.0000';
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
            return '0.0000';
        }

        return bcadd(
            $valorNormalizado,
            '0',
            4
        );
    }

    // ---------------------------------------------------------
    // DETERMINAR ESTADO
    // Define el estado de la reserva tras una ampliación.
    // ---------------------------------------------------------

    private function determinarEstado(
        ReservaInventario $reserva
    ): string {
        $procesada = bcadd(
            (string) $reserva->cantidad_consumida,
            (string) $reserva->cantidad_liberada,
            4
        );

        if (
            bccomp(
                $procesada,
                '0.0000',
                4
            ) > 0
        ) {
            return ReservaInventario::ESTADO_PARCIAL;
        }

        return ReservaInventario::ESTADO_ACTIVA;
    }
}
