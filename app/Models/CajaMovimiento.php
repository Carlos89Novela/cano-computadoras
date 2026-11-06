<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CajaMovimiento extends Model
{
    use HasFactory;

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

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'realizado_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CajaMovimiento $movimiento): void {
            if (empty($movimiento->uuid)) {
                $movimiento->uuid = (string) Str::uuid();
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

    public function turno(): BelongsTo
    {
        return $this->belongsTo(CajaTurno::class, 'caja_turno_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function autorizador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autorizado_por_id');
    }
}
