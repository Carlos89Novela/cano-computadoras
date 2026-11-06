<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class VentaPago extends Model
{
    use HasFactory;

    protected $table = 'venta_pagos';

    protected $fillable = [
        'uuid',
        'venta_id',
        'caja_turno_id',
        'forma_pago_sat',
        'monto',
        'monto_recibido',
        'cambio_devuelto',
        'referencia_bancaria',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'monto_recibido' => 'decimal:2',
        'cambio_devuelto' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (VentaPago $pago) {
            if (empty($pago->uuid)) {
                $pago->uuid = (string) Str::uuid();
            }
        });
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    public function cajaTurno(): BelongsTo
    {
        return $this->belongsTo(CajaTurno::class, 'caja_turno_id');
    }
}
