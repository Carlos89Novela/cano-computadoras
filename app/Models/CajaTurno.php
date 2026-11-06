<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CajaTurno extends Model
{
    use HasFactory;

    protected $table = 'caja_turnos';

    protected $fillable = [
        'uuid',
        'empresa_id',
        'sucursal_id',
        'user_id',
        'apertura_at',
        'cierre_at',
        'monto_inicial',
        'total_ventas_efectivo',
        'total_ventas_tarjeta',
        'total_ventas_transferencia',
        'total_otros_ingresos',
        'total_retiros',
        'monto_esperado_efectivo',
        'monto_real_efectivo',
        'diferencia_efectivo',
        'estado',
        'observaciones_apertura',
        'observaciones_cierre',
    ];

    protected $casts = [
        'apertura_at' => 'datetime',
        'cierre_at' => 'datetime',
        'monto_inicial' => 'decimal:2',
        'total_ventas_efectivo' => 'decimal:2',
        'total_ventas_tarjeta' => 'decimal:2',
        'total_ventas_transferencia' => 'decimal:2',
        'total_otros_ingresos' => 'decimal:2',
        'total_retiros' => 'decimal:2',
        'monto_esperado_efectivo' => 'decimal:2',
        'monto_real_efectivo' => 'decimal:2',
        'diferencia_efectivo' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (CajaTurno $turno) {
            if (empty($turno->uuid)) {
                $turno->uuid = (string) Str::uuid();
            }
        });
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class, 'caja_turno_id');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(VentaPago::class, 'caja_turno_id');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(CajaMovimiento::class, 'caja_turno_id');
    }
}
