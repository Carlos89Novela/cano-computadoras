<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    protected $table = 'clientes';

    protected $fillable = [
        'empresa_id',
        'nombre',
        'rfc',
        'correo',
        'telefono_whatsapp',
        'nivel_lealtad',
        'puntos_acumulados',
        'saldo_monedero',
        'activo',
    ];

    protected $casts = [
        'puntos_acumulados' => 'integer',
        'saldo_monedero' => 'decimal:2',
        'activo' => 'boolean',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function movimientosMonedero(): HasMany
    {
        return $this->hasMany(ClienteMonederoMovimiento::class, 'cliente_id');
    }
}