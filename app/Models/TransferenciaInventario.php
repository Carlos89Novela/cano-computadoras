<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Encabezado de una transferencia de inventario entre almacenes.
 *
 * @property int $id
 * @property int $empresa_id
 * @property int $almacen_origen_id
 * @property int $almacen_destino_id
 * @property string $grupo_id
 * @property string $folio
 * @property string $motivo
 * @property int $total_lineas
 * @property string $costo_total
 * @property int|null $creado_por_id
 * @property Carbon $transferido_at
 * @property-read Empresa $empresa
 * @property-read Almacen $almacenOrigen
 * @property-read Almacen $almacenDestino
 * @property-read User|null $creadoPor
 * @property-read Collection<int, TransferenciaInventarioDetalle> $detalles
 */
class TransferenciaInventario extends Model
{
    protected $table = 'transferencias_inventario';

    protected $fillable = [
        'empresa_id',
        'almacen_origen_id',
        'almacen_destino_id',
        'grupo_id',
        'folio',
        'motivo',
        'total_lineas',
        'costo_total',
        'creado_por_id',
        'transferido_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_lineas' => 'integer',
            'costo_total' => 'decimal:4',
            'transferido_at' => 'datetime',
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
     * @return BelongsTo<Almacen, $this>
     */
    public function almacenOrigen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class, 'almacen_origen_id');
    }

    /**
     * @return BelongsTo<Almacen, $this>
     */
    public function almacenDestino(): BelongsTo
    {
        return $this->belongsTo(Almacen::class, 'almacen_destino_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por_id');
    }

    /**
     * @return HasMany<TransferenciaInventarioDetalle, $this>
     */
    public function detalles(): HasMany
    {
        return $this->hasMany(TransferenciaInventarioDetalle::class, 'transferencia_inventario_id');
    }
}
