<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ReservaInventario extends Model
{
    // ---------------------------------------------------------
    // ESTADOS DE LA RESERVA
    // Centraliza los valores válidos utilizados para describir
    // el avance operativo de una reserva.
    // ---------------------------------------------------------

    public const ESTADO_ACTIVA = 'activa';

    public const ESTADO_PARCIAL = 'parcial';

    public const ESTADO_CONSUMIDA = 'consumida';

    public const ESTADO_LIBERADA = 'liberada';

    // ---------------------------------------------------------
    // TABLA
    // Define explícitamente la tabla utilizada por el modelo.
    // ---------------------------------------------------------

    protected $table = 'reservas_inventario';

    // ---------------------------------------------------------
    // ASIGNACIÓN MASIVA
    // Permite persistir los atributos controlados mediante las
    // acciones del módulo de inventario.
    // ---------------------------------------------------------

    protected $fillable = [
        'empresa_id',
        'almacen_id',
        'producto_id',
        'orden_servicio_id',
        'cantidad_reservada',
        'cantidad_consumida',
        'cantidad_liberada',
        'estado',
        'motivo',
        'reservado_por_id',
        'actualizado_por_id',
        'reservado_at',
        'finalizado_at',
    ];

    // ---------------------------------------------------------
    // CONVERSIÓN DE TIPOS
    // Mantiene cuatro decimales en las cantidades y convierte
    // las fechas operativas en objetos de fecha de Laravel.
    // ---------------------------------------------------------

    protected function casts(): array
    {
        return [
            'cantidad_reservada' => 'decimal:4',
            'cantidad_consumida' => 'decimal:4',
            'cantidad_liberada' => 'decimal:4',
            'reservado_at' => 'datetime',
            'finalizado_at' => 'datetime',
        ];
    }

    // ---------------------------------------------------------
    // ESTADOS VÁLIDOS
    // Devuelve el catálogo completo permitido para validar el
    // estado operativo de una reserva.
    // ---------------------------------------------------------

    /**
     * @return list<string>
     */
    public static function estadosValidos(): array
    {
        return [
            self::ESTADO_ACTIVA,
            self::ESTADO_PARCIAL,
            self::ESTADO_CONSUMIDA,
            self::ESTADO_LIBERADA,
        ];
    }

    // ---------------------------------------------------------
    // EMPRESA
    // Relaciona la reserva con la empresa propietaria.
    // ---------------------------------------------------------

    /**
     * @return BelongsTo<Empresa, $this>
     */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(
            Empresa::class
        );
    }

    // ---------------------------------------------------------
    // ALMACÉN
    // Identifica el almacén que conserva las unidades
    // comprometidas para la orden.
    // ---------------------------------------------------------

    /**
     * @return BelongsTo<Almacen, $this>
     */
    public function almacen(): BelongsTo
    {
        return $this->belongsTo(
            Almacen::class
        );
    }

    // ---------------------------------------------------------
    // PRODUCTO
    // Identifica el producto reservado para la reparación.
    // ---------------------------------------------------------

    /**
     * @return BelongsTo<Producto, $this>
     */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(
            Producto::class
        );
    }

    // ---------------------------------------------------------
    // ORDEN DE SERVICIO
    // Relaciona la reserva con la orden que requiere las
    // unidades comprometidas.
    // ---------------------------------------------------------

    /**
     * @return BelongsTo<OrdenServicio, $this>
     */
    public function ordenServicio(): BelongsTo
    {
        return $this->belongsTo(
            OrdenServicio::class
        );
    }

    // ---------------------------------------------------------
    // USUARIO QUE RESERVÓ
    // Identifica al usuario responsable de crear la reserva.
    // ---------------------------------------------------------

    /**
     * @return BelongsTo<User, $this>
     */
    public function reservadoPor(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'reservado_por_id'
        );
    }

    // ---------------------------------------------------------
    // USUARIO ACTUALIZADOR
    // Identifica al usuario que efectuó el último consumo o
    // liberación de unidades.
    // ---------------------------------------------------------

    /**
     * @return BelongsTo<User, $this>
     */
    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'actualizado_por_id'
        );
    }

    // ---------------------------------------------------------
    // CANTIDAD PENDIENTE
    // Calcula las unidades que continúan comprometidas después
    // de descontar consumos y liberaciones.
    // ---------------------------------------------------------

    public function cantidadPendiente(): string
    {
        $cantidadFinalizada = bcadd(
            (string) $this->cantidad_consumida,
            (string) $this->cantidad_liberada,
            4
        );

        return bcsub(
            (string) $this->cantidad_reservada,
            $cantidadFinalizada,
            4
        );
    }

    // ---------------------------------------------------------
    // RESERVA FINALIZADA
    // Una reserva está finalizada cuando no conserva ninguna
    // unidad pendiente de consumir o liberar.
    // ---------------------------------------------------------

    public function estaFinalizada(): bool
    {
        return bccomp(
            $this->cantidadPendiente(),
            '0.0000',
            4
        ) === 0;
    }

    // ---------------------------------------------------------
    // RESERVA ACTIVA
    // Comprueba si todavía existen unidades comprometidas y
    // el estado permite operaciones posteriores.
    // ---------------------------------------------------------

    public function estaActiva(): bool
    {
        return in_array(
            $this->estado,
            [
                self::ESTADO_ACTIVA,
                self::ESTADO_PARCIAL,
            ],
            true
        )
            && ! $this->estaFinalizada();
    }

    // ---------------------------------------------------------
    // PROTECCIÓN DE CONSISTENCIA
    // Impide guardar cantidades negativas, estados inválidos o
    // finalizar más unidades que las originalmente reservadas.
    // ---------------------------------------------------------

    protected static function booted(): void
    {
        static::saving(
            function (
                ReservaInventario $reserva
            ): void {
                if (
                    ! in_array(
                        $reserva->estado,
                        self::estadosValidos(),
                        true
                    )
                ) {
                    throw new LogicException(
                        'El estado de la reserva de inventario no es válido.'
                    );
                }

                if (
                    bccomp(
                        (string) $reserva->cantidad_reservada,
                        '0.0000',
                        4
                    ) !== 1
                ) {
                    throw new LogicException(
                        'La cantidad reservada debe ser mayor que cero.'
                    );
                }

                if (
                    bccomp(
                        (string) $reserva->cantidad_consumida,
                        '0.0000',
                        4
                    ) === -1
                ) {
                    throw new LogicException(
                        'La cantidad consumida no puede ser negativa.'
                    );
                }

                if (
                    bccomp(
                        (string) $reserva->cantidad_liberada,
                        '0.0000',
                        4
                    ) === -1
                ) {
                    throw new LogicException(
                        'La cantidad liberada no puede ser negativa.'
                    );
                }

                $cantidadFinalizada = bcadd(
                    (string) $reserva->cantidad_consumida,
                    (string) $reserva->cantidad_liberada,
                    4
                );

                if (
                    bccomp(
                        $cantidadFinalizada,
                        (string) $reserva->cantidad_reservada,
                        4
                    ) === 1
                ) {
                    throw new LogicException(
                        'La cantidad consumida y liberada no puede superar la cantidad reservada.'
                    );
                }
            }
        );
    }
}
