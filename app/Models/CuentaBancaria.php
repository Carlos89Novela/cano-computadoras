<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $empresa_id
 * @property int|null $sucursal_id
 * @property string $nombre_cuenta
 * @property string $banco
 * @property string|null $numero_cuenta
 * @property string|null $clabe
 * @property string $moneda
 * @property float $saldo_inicial
 * @property float $saldo_actual
 * @property bool $activa
 * @property string|null $notas
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Empresa $empresa
 * @property-read Sucursal|null $sucursal
 * @property-read Collection<int, MovimientoBancario> $movimientos
 */
class CuentaBancaria extends Model
{
    protected $table = 'cuentas_bancarias';

    protected $fillable = [
        'uuid',
        'empresa_id',
        'sucursal_id',
        'nombre_cuenta',
        'banco',
        'numero_cuenta',
        'clabe',
        'moneda',
        'saldo_inicial',
        'saldo_actual',
        'activa',
        'notas',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'saldo_inicial' => 'decimal:2',
            'saldo_actual' => 'decimal:2',
            'activa' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CuentaBancaria $cuenta): void {
            if (empty($cuenta->uuid)) {
                $cuenta->uuid = (string) Str::uuid();
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
     * @return BelongsTo<Sucursal, $this>
     */
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    /**
     * @return HasMany<MovimientoBancario, $this>
     */
    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoBancario::class, 'cuenta_bancaria_id');
    }
}
