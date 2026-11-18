<?php

namespace App\Models;

// ---------------------------------------------------------
// IMPORTACIONES DE ELOQUENT Y COLECCIONES
// ---------------------------------------------------------
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Gestión de promociones, descuentos y combos para marketing y POS.
 *
 * @property int $id
 * @property int $empresa_id
 * @property string $nombre
 * @property string $tipo
 * @property string|null $valor
 * @property Carbon|null $fecha_inicio
 * @property Carbon|null $fecha_fin
 * @property bool $activo
 * @property int $usos_actuales
 * @property int|null $limite_usos
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Empresa $empresa
 * @property-read Collection<int, PromocionItem> $items
 */
class Promocion extends Model
{
    use HasFactory;

    protected $table = 'promociones';

    protected $fillable = [
        'empresa_id',
        'nombre',
        'tipo',
        'valor',
        'fecha_inicio',
        'fecha_fin',
        'activo',
        'usos_actuales',
        'limite_usos',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'datetime',
            'fecha_fin' => 'datetime',
            'activo' => 'boolean',
            'usos_actuales' => 'integer',
            'limite_usos' => 'integer',
            'dias_semana' => 'array', // <-- Asegúrate de incluir esta línea
        ];
    }

    // =========================================================
    // MÉTODOS DE NEGOCIO Y VIGENCIA
    // =========================================================

    /**
     * Valida si la promoción se encuentra vigente para ser aplicada.
     */
    public function estaVigente(): bool
    {
        if (! $this->activo) {
            return false;
        }

        $now = now();

        $inicioValido = $this->fecha_inicio === null || $now->greaterThanOrEqualTo($this->fecha_inicio);
        $finValido = $this->fecha_fin === null || $now->lessThanOrEqualTo($this->fecha_fin);

        $limiteValido = true;
        if ($this->limite_usos !== null) {
            $limiteValido = (int) $this->usos_actuales < (int) $this->limite_usos;
        }

        return (bool) ($inicioValido && $finValido && $limiteValido);
    }

    /**
     * Alias requerido por los servicios de cálculo de promociones.
     */
    public function estaVigenteAhora(): bool
    {
        return $this->estaVigente();
    }

    // =========================================================
    // RELACIONES ELOQUENT
    // =========================================================

    /**
     * Empresa propietaria de la promoción.
     *
     * @return BelongsTo<Empresa, $this>
     */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /**
     * Ítems, productos o categorías participantes en la promoción.
     *
     * @return HasMany<PromocionItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PromocionItem::class, 'promocion_id');
    }
}
