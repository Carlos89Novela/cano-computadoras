<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClienteMonederoMovimiento extends Model
{
    protected $table = 'cliente_monedero_movimientos';

    protected $fillable = [
        'empresa_id',
        'cliente_id',
        'venta_id',
        'tipo',
        'monto_monedero',
        'puntos',
        'concepto',
    ];

    protected $casts = [
        'monto_monedero' => 'decimal:2',
        'puntos' => 'integer',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }
}