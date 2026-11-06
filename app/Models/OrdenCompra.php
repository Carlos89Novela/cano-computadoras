<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class OrdenCompra extends Model
{
    use HasFactory;

    protected $table = 'ordenes_compra';

    protected $fillable = [
        'uuid',
        'empresa_id',
        'sucursal_id',
        'almacen_id',
        'proveedor_id',
        'user_id',
        'autorizado_por_id',
        'folio',
        'estado',
        'fecha_emision',
        'fecha_entrega_esperada',
        'autorizada_at',
        'subtotal',
        'descuento_total',
        'iva_total',
        'total',
        'moneda',
        'tipo_cambio',
        'notas',
        'motivo_cancelacion',
    ];

    protected $casts = [
        'fecha_emision' => 'date',
        'fecha_entrega_esperada' => 'date',
        'autorizada_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'descuento_total' => 'decimal:2',
        'iva_total' => 'decimal:2',
        'total' => 'decimal:2',
        'tipo_cambio' => 'decimal:4',
    ];

    protected static function booted(): void
    {
        static::creating(function (OrdenCompra $orden): void {
            if (empty($orden->uuid)) {
                $orden->uuid = (string) Str::uuid();
            }
        });
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function almacen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class);
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function autorizador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autorizado_por_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(OrdenCompraDetalle::class, 'orden_compra_id');
    }
}
