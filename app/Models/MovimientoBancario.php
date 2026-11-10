<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $empresa_id
 * @property int $cuenta_bancaria_id
 * @property int $user_id
 * @property string $tipo
 * @property float $monto
 * @property float $saldo_anterior
 * @property float $saldo_posterior
 * @property Carbon $fecha_operacion
 * @property string|null $referencia
 * @property string $concepto
 * @property string|null $origen_type
 * @property int|null $origen_id
 * @property int|null $cuenta_destino_id
 * @property bool $conciliado
 * @property Carbon|null $conciliado_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Empresa $empresa
 * @property-read CuentaBancaria $cuentaBancaria
 * @property-read CuentaBancaria|null $cuentaDestino
 * @property-read User $usuario
 * @property-read Model|null $origen
 */
class MovimientoBancario extends Model
{
    protected $table = 'movimientos_bancarios';

    protected $fillable = [
        'uuid',
        'empresa_id',
        'cuenta_bancaria_id',
        'user_id',
        'tipo',
        'monto',
        'saldo_anterior',
        'saldo_posterior',
        'fecha_operacion',
        'referencia',
        'concepto',
        'origen_type',
        'origen_id',
        'cuenta_destino_id',
        'conciliado',
        'conciliado_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'saldo_anterior' => 'decimal:2',
            'saldo_posterior' => 'decimal:2',
            'fecha_operacion' => 'date',
            'conciliado' => 'boolean',
            'conciliado_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (MovimientoBancario $movimiento): void {
            if (empty($movimiento->uuid)) {
                $movimiento->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * @return BelongsTo<Empresa, $this>
     */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /**
     * @return BelongsTo<CuentaBancaria, $this>
     */
    public function cuentaBancaria(): BelongsTo
    {
        return $this->belongsTo(CuentaBancaria::class, 'cuenta_bancaria_id');
    }

    /**
     * @return BelongsTo<CuentaBancaria, $this>
     */
    public function cuentaDestino(): BelongsTo
    {
        return $this->belongsTo(CuentaBancaria::class, 'cuenta_destino_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function origen(): MorphTo
    {
        return $this->morphTo();
    }
}
