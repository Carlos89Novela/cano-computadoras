<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrdenServicio extends Model
{
    protected $table = 'orden_servicios';

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

    protected function casts(): array
    {
        return [
            'fecha_ingreso' => 'date',
            'fecha_entrega' => 'date',
            'fecha_autorizacion' => 'datetime',
            'costo_estimado' => 'decimal:2',
            'costo_final' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class);
    }

    public function historial(): HasMany
    {
        return $this->hasMany(HistorialReparacion::class, 'orden_servicio_id');
    }

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class);
    }
}