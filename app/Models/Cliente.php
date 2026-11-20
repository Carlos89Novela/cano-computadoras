<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo Eloquent para los clientes del sistema.
 * Centraliza datos fiscales CFDI 4.0, contacto, lealtad, monedero y relaciones
 * con la empresa y los movimientos de monedero electrónico.
 */
class Cliente extends Model
{
    // Nombre explícito de la tabla en base de datos
    protected $table = 'clientes';

    // Atributos permitidos para asignación masiva (alta, edición y homologación)
    protected $fillable = [
        'empresa_id',
        'nombre',
        'rfc',
        'codigo_postal',
        'regimen_fiscal',
        'uso_cfdi',
        'email',
        'telefono_whatsapp',
        'nivel_lealtad',
        'puntos_acumulados',
        'saldo_monedero',
        'activo',
    ];

    // Conversión automática de tipos al leer o escribir atributos
    protected $casts = [
        'puntos_acumulados' => 'integer',
        'saldo_monedero' => 'decimal:2',
        'activo' => 'boolean',
    ];

    /**
     * Obtiene la empresa a la que pertenece el cliente.
     */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /**
     * Obtiene los movimientos de puntos y saldo de monedero asociados al cliente.
     */
    public function movimientosMonedero(): HasMany
    {
        return $this->hasMany(ClienteMonederoMovimiento::class, 'cliente_id');
    }
}
