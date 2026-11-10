<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Venta extends Model
{
    use HasFactory;

    protected $table = 'ventas';

    protected $fillable = [
        'uuid',
        'empresa_id',
        'sucursal_id',
        'almacen_id',
        'caja_turno_id',
        'user_id',
        'cliente_id',
        'folio',
        'cliente_nombre',
        'cliente_rfc',
        'cliente_email',
        'cliente_telefono',
        'cliente_regimen_fiscal',
        'cliente_codigo_postal',
        'subtotal_sin_impuestos',
        'descuento_total',
        'base_imponible',
        'iva_total',
        'total',
        'costo_total_inventario',
        'forma_pago_sat',
        'metodo_pago_sat',
        'uso_cfdi_sat',
        'estado',
        'origen_registro',
        'sincronizado_at',
        'estado_facturacion',
        'uuid_fiscal',
        'serie_folio_factura',
        'xml_path',
        'pdf_factura_path',
        'facturado_at',
        'notas',
        'token_autofactura',
        'autofactura_limite_at',
        'facturacion_cfdi_id',
        'factura_global_id',
        'uuid_factura_global',
        'es_factura_global',
        'periodicidad_global',
    ];

    protected $casts = [
        'sincronizado_at' => 'datetime',
        'facturado_at' => 'datetime',
        'subtotal_sin_impuestos' => 'decimal:4',
        'descuento_total' => 'decimal:4',
        'base_imponible' => 'decimal:4',
        'iva_total' => 'decimal:4',
        'total' => 'decimal:2',
        'costo_total_inventario' => 'decimal:4',
        'autofactura_limite_at' => 'datetime',
        'es_factura_global' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Venta $venta) {
            if (empty($venta->uuid)) {
                $venta->uuid = (string) Str::uuid();
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

    public function cajaTurno(): BelongsTo
    {
        return $this->belongsTo(CajaTurno::class, 'caja_turno_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cliente_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(VentaDetalle::class, 'venta_id');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(VentaPago::class, 'venta_id');
    }
}
