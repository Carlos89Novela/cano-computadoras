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
 * @property string $nombre
 * @property int $orden
 * @property bool $activo
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Empresa $empresa
 * @property-read Sucursal $sucursal
 * @property-read Collection<int, RestauranteMesa> $mesas
 */
class RestauranteArea extends Model
{
    protected $table = 'restaurante_areas';

    protected $fillable = [
        'empresa_id',
        'sucursal_id',
        'nombre',
        'orden',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'activo' => 'boolean',
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
     * @return HasMany<RestauranteMesa, $this>
     */
    public function mesas(): HasMany
    {
        return $this->hasMany(RestauranteMesa::class, 'restaurante_area_id');
    }
}
