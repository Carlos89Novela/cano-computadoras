<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CajaMovimiento extends Model
{
    protected $table = 'caja_movimientos';

    protected $fillable = [
        'uuid',
        'empresa_id',
        'sucursal_id',
        'caja_turno_id',
        'user_id',
        'autorizado_por_id',
        'tipo',
        'concepto',
        'monto',
        'motivo',
        'comprobante_numero',
        'realizado_at',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'realizado_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (CajaMovimiento $movimiento) {
            if (empty($movimiento->uuid)) {
                $movimiento->uuid = (string) Str::uuid();
            }
        });
    }

    public function turno(): BelongsTo
    {
        return $this->belongsTo(CajaTurno::class, 'caja_turno_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}