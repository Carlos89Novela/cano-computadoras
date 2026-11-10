<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $empresa_id
 * @property int $sucursal_id
 * @property int|null $venta_id
 * @property int|null $mesero_user_id
 * @property string $folio
 * @property string $mesa_o_referencia
 * @property string $estado
 * @property string|null $notas_generales
 * @property Carbon $enviada_a_cocina_at
 * @property Carbon|null $finalizada_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Empresa $empresa
 * @property-read Sucursal $sucursal
 * @property-read Venta|null $venta
 * @property-read User|null $mesero
 * @property-read Collection<int, ComandaItem> $items
 */
class Comanda extends Model
{
    protected $table = 'comandas';

    protected $fillable = [
        'empresa_id',
        'sucursal_id',
        'venta_id',
        'mesero_user_id',
        'folio',
        'mesa_o_referencia',
        'estado',
        'notas_generales',
        'enviada_a_cocina_at',
        'finalizada_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enviada_a_cocina_at' => 'datetime',
            'finalizada_at' => 'datetime',
        ];
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
     * @return BelongsTo<Venta, $this>
     */
    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function mesero(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mesero_user_id');
    }

    /**
     * @return HasMany<ComandaItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ComandaItem::class, 'comanda_id');
    }
}
