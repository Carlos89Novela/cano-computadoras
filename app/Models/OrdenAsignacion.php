<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $asignado_at
 * @property Carbon|null $finalizado_at
 */
class OrdenAsignacion extends Model
{
    protected $table = 'orden_asignaciones';

    protected $fillable = [
        'orden_servicio_id',
        'empleado_id',
        'asignado_por_id',
        'asignado_at',
        'finalizado_at',
        'activo',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'asignado_at' => 'datetime',
            'finalizado_at' => 'datetime',
            'activo' => 'boolean',
        ];
    }

    /**
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
