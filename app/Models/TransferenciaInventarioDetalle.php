<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Línea de detalle de una transferencia de inventario.
 *
 * @property int $id
 * @property int $transferencia_inventario_id
 * @property int $producto_id
 * @property string $cantidad
 * @property string $cantidad_solicitada
 * @property string $cantidad_despachada
 * @property string $cantidad_recibida
 * @property string $costo_unitario
 * @property string $subtotal
 * @property-read TransferenciaInventario $transferencia
 * @property-read Producto $producto
 */
class TransferenciaInventarioDetalle extends Model
{
    protected $table = 'transferencia_inventario_detalles';

    protected $fillable = [
        'transferencia_inventario_id',
        'producto_id',
        'cantidad',
        'cantidad_solicitada',
        'cantidad_despachada',
        'cantidad_recibida',
        'costo_unitario',
        'subtotal',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:4',
            'cantidad_solicitada' => 'decimal:4',
            'cantidad_despachada' => 'decimal:4',
            'cantidad_recibida' => 'decimal:4',
            'costo_unitario' => 'decimal:4',
            'subtotal' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<TransferenciaInventario, $this>
     */
    public function transferencia(): BelongsTo
    {
        return $this->belongsTo(TransferenciaInventario::class, 'transferencia_inventario_id');
    }

    /**
     * @return BelongsTo<Producto, $this>
     */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
