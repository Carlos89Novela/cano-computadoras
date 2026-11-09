<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $empresa_id
 * @property int $producto_id
 * @property string $rendimiento_porciones
 * @property int $tiempo_preparacion_minutos
 * @property string $estacion_cocina
 * @property string|null $instrucciones
 * @property bool $activo
 * @property-read Producto $producto
 * @property-read Collection<int, ProductoRecetaDetalle> $ingredientes
 */
class ProductoReceta extends Model
{
    protected $table = 'producto_recetas';

    protected $fillable = [
        'empresa_id',
        'producto_id',
        'rendimiento_porciones',
        'tiempo_preparacion_minutos',
        'estacion_cocina',
        'instrucciones',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rendimiento_porciones' => 'decimal:2',
            'tiempo_preparacion_minutos' => 'integer',
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
     * @return BelongsTo<Producto, $this>
     */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    /**
     * @return HasMany<ProductoRecetaDetalle, $this>
     */
    public function ingredientes(): HasMany
    {
        return $this->hasMany(ProductoRecetaDetalle::class, 'producto_receta_id');
    }
}
