<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $restaurante_area_id
 * @property string $numero_o_nombre
 * @property int $capacidad
 * @property string $forma
 * @property string $posicion_x
 * @property string $posicion_y
 * @property string $estado
 * @property int|null $comanda_actual_id
 * @property bool $activo
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read RestauranteArea $area
 * @property-read Comanda|null $comandaActual
 */
class RestauranteMesa extends Model
{
    protected $table = 'restaurante_mesas';

    protected $fillable = [
        'restaurante_area_id',
        'numero_o_nombre',
        'capacidad',
        'forma',
        'posicion_x',
        'posicion_y',
        'estado',
        'comanda_actual_id',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'capacidad' => 'integer',
            'posicion_x' => 'decimal:2',
            'posicion_y' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<RestauranteArea, $this>
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(RestauranteArea::class, 'restaurante_area_id');
    }

    /**
     * @return BelongsTo<Comanda, $this>
     */
    public function comandaActual(): BelongsTo
    {
        return $this->belongsTo(Comanda::class, 'comanda_actual_id');
    }
}
