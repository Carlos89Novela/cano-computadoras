<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $comanda_id
 * @property int $producto_id
 * @property string $cantidad
 * @property string $estacion_cocina
 * @property string $estado
 * @property string|null $notas_especiales
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Comanda $comanda
 * @property-read Producto $producto
 */
class ComandaItem extends Model
{
    protected $table = 'comanda_items';

    protected $fillable = [
        'comanda_id',
        'producto_id',
        'cantidad',
        'estacion_cocina',
        'estado',
        'notas_especiales',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Comanda, $this>
     */
    public function comanda(): BelongsTo
    {
        return $this->belongsTo(Comanda::class);
    }

    /**
     * @return BelongsTo<Producto, $this>
     */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
