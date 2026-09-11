<?php

namespace App\Models;

use App\Enums\EstadoRevisionCotizacion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property Carbon $fecha_ingreso
 * @property Carbon|null $fecha_entrega
 * @property Carbon|null $fecha_autorizacion
 * @property EstadoRevisionCotizacion $estado_revision_cotizacion
 * @property Carbon|null $cotizacion_revisada_at
 */
class OrdenServicio extends Model
{
    protected $table = 'orden_servicios';

    protected $fillable = [
        'folio',
        'token_seguimiento',
        'user_id',
        'equipo_id',
        'servicio_id',
        'problema_reportado',
        'diagnostico',
        'costo_estimado',
        'estado_revision_cotizacion',
        'cotizacion_revisada_por_id',
        'cotizacion_revisada_at',
        'observacion_revision_cotizacion',
        'costo_final',
        'estado',
        'autorizacion',
        'fecha_autorizacion',
        'fecha_ingreso',
        'fecha_entrega',

    ];

    protected static function booted(): void
    {
        static::creating(function (OrdenServicio $orden): void {
            if (blank($orden->token_seguimiento)) {
                $orden->token_seguimiento = (string) Str::ulid();
            }
        });
    }

    /**
     * Conversiones automáticas de atributos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_autorizacion' => 'datetime',
            'fecha_ingreso' => 'date',
            'fecha_entrega' => 'date',
            'costo_estimado' => 'decimal:2',
            'estado_revision_cotizacion' => EstadoRevisionCotizacion::class,
            'cotizacion_revisada_at' => 'datetime',
            'costo_final' => 'decimal:2',
        ];
    }

    /**
     * Usuario relacionado con la orden.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Equipo relacionado con la orden.
     *
     * @return BelongsTo<Equipo, $this>
     */
    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class);
    }

    /**
     * Servicio relacionado con la orden.
     *
     * @return BelongsTo<Servicio, $this>
     */
    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class);
    }

    /**
     * Ususario que realizó la revisión interna de la cotización.
     *
     * @return BelongsTo<User, $this>
     */
    public function cotizacionRevisadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cotizacion_revisada_por_id');
    }

    /**
     * Registros del historial de reparación.
     *
     * @return HasMany<HistorialReparacion, $this>
     */
    public function historial(): HasMany
    {
        return $this->hasMany(
            HistorialReparacion::class,
            'orden_servicio_id'
        )->latest('created_at');
    }

    /**
     * @return HasMany<OrdenAsignacion, $this>
     */
    public function asignaciones(): HasMany
    {
        return $this->hasMany(
            OrdenAsignacion::class,
            'orden_servicio_id'
        );
    }

    /**
     * @return HasOne<OrdenAsignacion, $this>
     */
    public function asignacionActiva(): HasOne
    {
        return $this->hasOne(
            OrdenAsignacion::class,
            'orden_servicio_id'
        )
            ->where('activo', true)
            ->latestOfMany('asignado_at');
    }
}
