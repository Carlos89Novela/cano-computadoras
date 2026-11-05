<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class VentaDetalle extends Model
{
    use HasFactory;

    protected $table = 'venta_detalles';

    protected $fillable = [
        'uuid',
        'venta_id',
        'producto_id',
        'almacen_id',
        'codigo_barras',
        'sku',
        'descripcion_producto',
        'clave_sat_producto',
        'clave_sat_unidad',
        'cantidad',
        'costo_unitario_promedio',
        'precio_unitario',
        'descuento_unitario',
        'tasa_iva',
        'monto_iva_unitario',
        'subtotal',
        'total',
    ];

    protected $casts = [
        'cantidad' => 'decimal:4',
        'costo_unitario_promedio' => 'decimal:4',
        'precio_unitario' => 'decimal:4',
        'descuento_unitario' => 'decimal:4',
        'tasa_iva' => 'decimal:4',
        'monto_iva_unitario' => 'decimal:4',
        'subtotal' => 'decimal:4',
        'total' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (VentaDetalle $detalle) {
            if (empty($detalle->uuid)) {
                $detalle->uuid = (string) Str::uuid();
            }
        });
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function almacen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class, 'almacen_id');
    }
}
