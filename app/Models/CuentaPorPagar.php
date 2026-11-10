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
 * @property int $proveedor_id
 * @property int|null $recepcion_inventario_id
 * @property int|null $orden_compra_id
 * @property string|null $uuid_sat
 * @property string|null $serie
 * @property string|null $folio
 * @property string|null $xml_path
 * @property Carbon $fecha_emision
 * @property Carbon $fecha_vencimiento
 * @property float $subtotal
 * @property float $iva_total
 * @property float $total
 * @property float $monto_pagado
 * @property float $saldo_pendiente
 * @property string $estado
 * @property string|null $notas
 * @property int $registrado_por_user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Empresa $empresa
 * @property-read Sucursal $sucursal
 * @property-read Proveedor $proveedor
 * @property-read RecepcionInventario|null $recepcionInventario
 * @property-read OrdenCompra|null $ordenCompra
 * @property-read User $registradoPor
 * @property-read Collection<int, CuentaPorPagarPago> $pagos
 */
class CuentaPorPagar extends Model
{
    protected $table = 'cuentas_por_pagar';

    protected $fillable = [
        'empresa_id',
        'sucursal_id',
        'proveedor_id',
        'recepcion_inventario_id',
        'orden_compra_id',
        'uuid_sat',
        'serie',
        'folio',
        'xml_path',
        'fecha_emision',
        'fecha_vencimiento',
        'subtotal',
        'iva_total',
        'total',
        'monto_pagado',
        'saldo_pendiente',
        'estado',
        'notas',
        'registrado_por_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_emision' => 'datetime',
            'fecha_vencimiento' => 'date',
            'subtotal' => 'decimal:4',
            'iva_total' => 'decimal:4',
            'total' => 'decimal:4',
            'monto_pagado' => 'decimal:4',
            'saldo_pendiente' => 'decimal:4',
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
     * @return BelongsTo<Proveedor, $this>
     */
    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    /**
     * @return BelongsTo<RecepcionInventario, $this>
     */
    public function recepcionInventario(): BelongsTo
    {
        return $this->belongsTo(RecepcionInventario::class, 'recepcion_inventario_id');
    }

    /**
     * @return BelongsTo<OrdenCompra, $this>
     */
    public function ordenCompra(): BelongsTo
    {
        return $this->belongsTo(OrdenCompra::class, 'orden_compra_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_user_id');
    }

    /**
     * @return HasMany<CuentaPorPagarPago, $this>
     */
    public function pagos(): HasMany
    {
        return $this->hasMany(CuentaPorPagarPago::class, 'cuenta_por_pagar_id');
    }
}
