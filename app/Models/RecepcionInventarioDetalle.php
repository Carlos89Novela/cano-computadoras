<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Línea individual de una recepción múltiple de inventario.
 *
 * @property int $id
 * @property int $recepcion_inventario_id
 * @property int $producto_id
 * @property string $cantidad
 * @property string $costo_unitario
 * @property string $subtotal
 * @property-read RecepcionInventario $recepcion
 * @property-read Producto $producto
 */
class RecepcionInventarioDetalle extends Model
{
    // ---------------------------------------------------------
    // TABLA
    // Declara explícitamente la tabla que almacena las líneas
    // de cada recepción de inventario.
    // ---------------------------------------------------------

    protected $table = 'recepcion_inventario_detalles';

    // ---------------------------------------------------------
    // ASIGNACIÓN MASIVA
    // Limita los campos que pueden establecerse al registrar
    // cada línea de la recepción.
    // ---------------------------------------------------------

    protected $fillable = [
        'recepcion_inventario_id',
        'producto_id',
        'cantidad',
        'costo_unitario',
        'subtotal',
    ];

    // ---------------------------------------------------------
    // CONVERSIONES
    // Mantiene cuatro posiciones decimales en cantidades,
    // costos unitarios y subtotales.
    // ---------------------------------------------------------

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:4',
            'costo_unitario' => 'decimal:4',
            'subtotal' => 'decimal:4',
        ];
    }

    // ---------------------------------------------------------
    // RECEPCIÓN
    // Relaciona esta línea con el encabezado del documento.
    // ---------------------------------------------------------

    /**
     * @return BelongsTo<RecepcionInventario, $this>
     */
    public function recepcion(): BelongsTo
    {
        return $this->belongsTo(
            RecepcionInventario::class,
            'recepcion_inventario_id'
        );
    }

    // ---------------------------------------------------------
    // PRODUCTO
    // Identifica el producto incluido en esta línea.
    // ---------------------------------------------------------

    /**
     * @return BelongsTo<Producto, $this>
     */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(
            Producto::class
        );
    }
}
