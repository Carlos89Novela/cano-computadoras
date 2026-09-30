<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Modelo Eloquent que registra la bitácora o historial de eventos de una Orden de Servicio.
 *
 * Cada registro documenta un cambio de estado, diagnóstico técnico, notas internas de taller
 * o mensajes públicos destinados al cliente.
 *
 * @property int $id
 * @property int $orden_servicio_id Orden de servicio asociada
 * @property int|null $user_id Usuario (técnico/supervisor) que emitió la entrada
 * @property string $estado Estado de la orden al momento de la entrada
 * @property string|null $comentarios Comentarios o notas técnicas internas de taller (confidenciales)
 * @property string|null $mensaje_cliente Mensaje público visible para el cliente en el portal de seguimiento
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class HistorialReparacion extends Model
{
    /**
     * Nombre explícito de la tabla en base de datos.
     *
     * @var string
     */
    protected $table = 'historial_reparaciones';

    /**
     * Columnas asignables en masa.
     *
     * @var list<string>
     */
    protected $fillable = [
        'orden_servicio_id',
        'user_id',
        'estado',
        'comentarios',
        'mensaje_cliente',
    ];

    /**
     * Relación: Orden de servicio a la que pertenece esta entrada del historial.
     *
     * @return BelongsTo<OrdenServicio, $this>
     */
    public function ordenServicio(): BelongsTo
    {
        return $this->belongsTo(
            OrdenServicio::class,
            'orden_servicio_id'
        );
    }

    /**
     * Relación: Usuario (empleado o supervisor) que registró la entrada en el historial.
     *
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'user_id'
        );
    }
}
