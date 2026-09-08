<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistorialReparacion extends Model
{
    protected $table = 'historial_reparaciones';

    protected $fillable = [
        'orden_servicio_id',
        'user_id',
        'estado',
        'comentarios',
        'mensaje_cliente',
    ];

    /**
     * Orden de servicio relacionada con el historial.
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
     * Usuario que registró el cambio.
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
