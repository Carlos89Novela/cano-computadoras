<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Encabezado de una transferencia de inventario por requisición y custodia en tránsito.
 *
 * @property int $id
 * @property int $empresa_id
 * @property int $almacen_origen_id
 * @property int $almacen_destino_id
 * @property int|null $almacen_transito_id
 * @property string $grupo_id
 * @property string $folio
 * @property string $estado
 * @property string $motivo
 * @property string|null $observaciones_despacho
 * @property string|null $observaciones_recepcion
 * @property int $total_lineas
 * @property string $costo_total
 * @property int|null $creado_por_id
 * @property int|null $solicitado_por_id
 * @property int|null $despachado_por_id
 * @property int|null $recibido_por_id
 * @property Carbon $transferido_at
 * @property Carbon|null $solicitado_at
 * @property Carbon|null $despachado_at
 * @property Carbon|null $recibido_at
 * @property-read Empresa $empresa
 * @property-read Almacen $almacenOrigen
 * @property-read Almacen $almacenDestino
 * @property-read Almacen|null $almacenTransito
 * @property-read User|null $creadoPor
 * @property-read User|null $solicitadoPor
 * @property-read User|null $despachadoPor
 * @property-read User|null $recibidoPor
 * @property-read Collection<int, TransferenciaInventarioDetalle> $detalles
 */
class TransferenciaInventario extends Model
{
    public const ESTADO_SOLICITADA = 'solicitada';

    public const ESTADO_EN_TRANSITO = 'en_transito';

    public const ESTADO_COMPLETADA = 'completada';

    public const ESTADO_CANCELADA = 'cancelada';

    protected $table = 'transferencias_inventario';

    protected $fillable = [
        'empresa_id',
        'almacen_origen_id',
        'almacen_destino_id',
        'almacen_transito_id',
        'grupo_id',
        'folio',
        'estado',
        'motivo',
        'observaciones_despacho',
        'observaciones_recepcion',
        'total_lineas',
        'costo_total',
        'creado_por_id',
        'solicitado_por_id',
        'despachado_por_id',
        'recibido_por_id',
        'transferido_at',
        'solicitado_at',
        'despachado_at',
        'recibido_at',
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
            'solicitado_at' => 'datetime',
            'despachado_at' => 'datetime',
            'recibido_at' => 'datetime',
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
     * @return BelongsTo<Almacen, $this>
     */
    public function almacenTransito(): BelongsTo
    {
        return $this->belongsTo(Almacen::class, 'almacen_transito_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function solicitadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitado_por_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function despachadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'despachado_por_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recibidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recibido_por_id');
    }

    /**
     * @return HasMany<TransferenciaInventarioDetalle, $this>
     */
    public function detalles(): HasMany
    {
        return $this->hasMany(TransferenciaInventarioDetalle::class, 'transferencia_inventario_id');
    }
}
