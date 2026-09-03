<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrdenServicio extends Model
{
    /**
     * Nombre de la tabla asociada al modelo.
     *
     * @var string
     */
    protected $table = 'orden_servicios';

    /**
     * Atributos permitidos para asignación masiva.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'folio',
        'user_id',
        'equipo_id',
        'servicio_id',
        'problema_reportado',
        'diagnostico',
        'costo_estimado',
        'costo_final',
        'estado',
        'autorizacion',
        'fecha_autorizacion',
        'fecha_ingreso',
        'fecha_entrega',
    ];

    /**
     * Conversiones automáticas de atributos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'autorizacion' => 'boolean',
            'fecha_autorizacion' => 'datetime',
            'fecha_ingreso' => 'date',
            'fecha_entrega' => 'date',
            'costo_estimado' => 'decimal:2',
            'costo_final' => 'decimal:2',
        ];
    }

    /**
     * Usuario relacionado con la orden.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Equipo relacionado con la orden.
     */
    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class);
    }

    /**
     * Servicio relacionado con la orden.
     */
    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class);
    }

    /**
     * Registros del historial de reparación.
     */
    public function historial(): HasMany
    {
        return $this->hasMany(
            HistorialReparacion::class,
            'orden_servicio_id'
        );
    }
}
