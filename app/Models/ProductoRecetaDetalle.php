<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $producto_receta_id
 * @property int $insumo_producto_id
 * @property string $cantidad_requerida
 * @property string $porcentaje_merma
 * @property-read ProductoReceta $receta
 * @property-read Producto $insumo
 */
class ProductoRecetaDetalle extends Model
{
    protected $table = 'producto_receta_detalles';

    protected $fillable = [
        'producto_receta_id',
        'insumo_producto_id',
        'cantidad_requerida',
        'porcentaje_merma',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad_requerida' => 'decimal:4',
            'porcentaje_merma' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<ProductoReceta, $this>
     */
    public function receta(): BelongsTo
    {
        return $this->belongsTo(ProductoReceta::class, 'producto_receta_id');
    }

    /**
     * @return BelongsTo<Producto, $this>
     */
    public function insumo(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'insumo_producto_id');
    }
}
