<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Modelo Eloquent que gestiona la asignación de técnicos a Órdenes de Servicio.
 *
 * Permite registrar qué empleado técnico es responsable de una orden, quién lo asignó,
 * la fecha de inicio, la fecha de término y las observaciones de la asignación.
 * Solo puede haber una asignación activa por orden de servicio a la vez.
 *
 * @property int $id
 * @property int $orden_servicio_id Orden de servicio asignada
 * @property int $empleado_id Usuario empleado que atiende la orden
 * @property int $asignado_por_id Usuario (supervisor/administrador) que realizó la asignación
 * @property Carbon $asignado_at Fecha y hora de inicio de la asignación
 * @property Carbon|null $finalizado_at Fecha y hora de conclusión o reasignación
 * @property bool $activo Indica si la asignación está vigente
 * @property string|null $observaciones Instrucciones o notas para el técnico
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class OrdenAsignacion extends Model
{
    /**
     * Nombre explícito de la tabla en base de datos.
     *
     * @var string
     */
    protected $table = 'orden_asignaciones';

    /**
     * Columnas asignables en masa.
     *
     * @var list<string>
     */
    protected $fillable = [
        'orden_servicio_id',
        'empleado_id',
        'asignado_por_id',
        'asignado_at',
        'finalizado_at',
        'activo',
        'observaciones',
    ];

    /**
     * Conversiones de tipo automáticas.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'asignado_at' => 'datetime',
            'finalizado_at' => 'datetime',
            'activo' => 'boolean',
        ];
    }

    /**
     * Relación: Orden de servicio asignada.
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
     * Relación: Empleado técnico responsable del trabajo.
     *
     * @return BelongsTo<User, $this>
     */
    public function empleado(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'empleado_id'
        );
    }

    /**
     * Relación: Supervisor o administrador que asignó la orden al técnico.
     *
     * @return BelongsTo<User, $this>
     */
    public function asignadoPor(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'asignado_por_id'
        );
    }
}
