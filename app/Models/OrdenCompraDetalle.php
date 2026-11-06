<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class OrdenCompraDetalle extends Model
{
    use HasFactory;

    protected $table = 'orden_compra_detalles';

    protected $fillable = [
        'uuid',
        'orden_compra_id',
        'producto_id',
        'cantidad',
        'cantidad_recibida',
        'costo_unitario',
        'descuento_unitario',
        'tasa_iva',
        'monto_iva_unitario',
        'subtotal',
        'total',
    ];

    protected $casts = [
        'cantidad' => 'decimal:4',
        'cantidad_recibida' => 'decimal:4',
        'costo_unitario' => 'decimal:4',
        'descuento_unitario' => 'decimal:4',
        'tasa_iva' => 'decimal:4',
        'monto_iva_unitario' => 'decimal:4',
        'subtotal' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (OrdenCompraDetalle $detalle): void {
            if (empty($detalle->uuid)) {
                $detalle->uuid = (string) Str::uuid();
            }
        });
    }

    public function ordenCompra(): BelongsTo
    {
        return $this->belongsTo(OrdenCompra::class, 'orden_compra_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
