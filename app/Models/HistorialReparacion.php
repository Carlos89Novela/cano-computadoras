<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistorialReparacion extends Model
{
    // Definir la tabla asociada al modelo
    protected $table = 'historial_reparaciones';

    protected $fillable = [
        'orden_servicio_id',
        'user_id',
        'estado',
        'comentarios',
    ];
    // Definir la relación con el modelo OrdenServicio
    public function ordenServicio(): BelongsTo
    {
        return $this->belongsTo(
            OrdenServicio::class,
            'orden_servicio_id'
        );
    }
    // Definir la relación con el modelo User
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'user_id'
        );
    }
}