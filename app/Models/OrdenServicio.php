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
 * Modelo Eloquent principal del Taller: Orden de Servicio.
 *
 * Administra el ciclo de vida completo de una reparación técnica:
 * Recepción -> Diagnóstico -> Cotización -> Aprobación -> Pruebas -> Entrega.
 *
 * Características clave:
 * 1. Folio único de identificación para taller (ej. OS-202609-0001).
 * 2. Token criptográfico ULID (token_seguimiento) para consulta pública del cliente vía QR.
 * 3. Proceso de revisión y supervisión interna de cotizaciones antes de notificar al cliente.
 * 4. Historial cronológico de cambios de estado y notas técnicas.
 * 5. Asignación activa de técnicos para el despacho de trabajo.
 *
 * @property int $id
 * @property string $folio Folio correlativo del taller
 * @property string $token_seguimiento Token ULID único para consulta pública en portal de seguimiento
 * @property int $user_id Cliente propietario del servicio
 * @property int $equipo_id Equipo que se recibe a reparación
 * @property int|null $servicio_id Tipo de servicio técnico solicitado
 * @property string $problema_reportado Falla descrita por el cliente en ventanilla
 * @property string|null $diagnostico Diagnóstico elaborado por el técnico
 * @property string|null $costo_estimado Presupuesto preliminar calculado
 * @property EstadoRevisionCotizacion $estado_revision_cotizacion Estado de la cotización interna
 * @property int|null $cotizacion_revisada_por_id Supervisor que revisó la cotización
 * @property Carbon|null $cotizacion_revisada_at Fecha de revisión de cotización
 * @property string|null $observacion_revision_cotizacion Observaciones del supervisor
 * @property string|null $costo_final Importe real facturado/cobrado
 * @property string $estado Estado actual de la orden (Recibido, En Diagnostico, etc.)
 * @property string|null $autorizacion Estado de autorización del cliente
 * @property Carbon|null $fecha_autorizacion
 * @property Carbon $fecha_ingreso
 * @property Carbon|null $fecha_entrega
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class OrdenServicio extends Model
{
    /**
     * Nombre explícito de la tabla en base de datos.
     *
     * @var string
     */
    protected $table = 'orden_servicios';

    /**
     * Columnas asignables en masa.
     *
     * @var list<string>
     */
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

    /**
     * Ciclo de vida del modelo: Al crear una orden, genera automáticamente un token ULID
     * de 26 caracteres alfanuméricos si no fue proporcionado.
     */
    protected static function booted(): void
    {
        static::creating(function (OrdenServicio $orden): void {
            if (blank($orden->token_seguimiento)) {
                $orden->token_seguimiento = (string) Str::ulid();
            }
        });
    }

    /**
     * Conversiones automáticas de tipos de atributos.
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
     * Relación: Cliente propietario de la orden de servicio.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relación: Equipo físico que se está reparando.
     *
     * @return BelongsTo<Equipo, $this>
     */
    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class);
    }

    /**
     * Relación: Catálogo del servicio base acordado.
     *
     * @return BelongsTo<Servicio, $this>
     */
    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class);
    }

    /**
     * Relación: Supervisor o administrador que revisó internamente la cotización.
     *
     * @return BelongsTo<User, $this>
     */
    public function cotizacionRevisadaPor(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'cotizacion_revisada_por_id'
        );
    }

    /**
     * Relación: Bitácora de cambios de estado y notas técnicas de la orden.
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
     * Relación: Histórico de todas las asignaciones a técnicos de la orden.
     *
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
     * Relación: Asignación técnica actualmente activa y vigente.
     *
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
