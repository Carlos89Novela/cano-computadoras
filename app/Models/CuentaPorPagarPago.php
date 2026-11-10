<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $cuenta_por_pagar_id
 * @property int $user_id
 * @property Carbon $fecha_pago
 * @property float $monto
 * @property string $forma_pago_sat
 * @property string|null $referencia_bancaria
 * @property string|null $comprobante_path
 * @property string|null $notas
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read CuentaPorPagar $cuentaPorPagar
 * @property-read User $usuario
 */
class CuentaPorPagarPago extends Model
{
    protected $table = 'cuenta_por_pagar_pagos';

    protected $fillable = [
        'cuenta_por_pagar_id',
        'user_id',
        'fecha_pago',
        'monto',
        'forma_pago_sat',
        'referencia_bancaria',
        'comprobante_path',
        'notas',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_pago' => 'date',
            'monto' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<CuentaPorPagar, $this>
     */
    public function cuentaPorPagar(): BelongsTo
    {
        return $this->belongsTo(CuentaPorPagar::class, 'cuenta_por_pagar_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
