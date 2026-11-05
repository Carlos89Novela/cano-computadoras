<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Modelo Eloquent que representa un Equipo electrónico recibido para servicio técnico.
 *
 * Pertenece a un cliente (User) y puede tener múltiples órdenes de servicio asociadas
 * a lo largo de su ciclo de vida.
 *
 * @property int $id
 * @property int $user_id Propietario / Cliente del equipo
 * @property string $tipo Tipo de equipo (laptop, desktop, impresora, etc.)
 * @property string $marca Marca del fabricante (Dell, HP, Lenovo, etc.)
 * @property string $modelo Modelo comercial o código de producto
 * @property string|null $numero_serie Número de serie único del fabricante
 * @property string|null $descripcion Detalles físicos, accesorios entregados o señas particulares
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Equipo extends Model
{
    /**
     * Columnas asignables en masa.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'tipo',
        'marca',
        'modelo',
        'numero_serie',
        'descripcion',
    ];

    /**
     * Relación: Cliente propietario del equipo.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relación: Órdenes de servicio o reparaciones vinculadas a este equipo.
     *
     * @return HasMany<OrdenServicio, $this>
     */
    public function ordenesServicio(): HasMany
    {
        return $this->hasMany(OrdenServicio::class);
    }
}
