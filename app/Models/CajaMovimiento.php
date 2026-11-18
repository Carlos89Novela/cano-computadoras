<?php

namespace App\Models;

// ---------------------------------------------------------
// IMPORTACIONES DE ELOQUENT Y UTILIDADES
// ---------------------------------------------------------
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Movimientos extraordinarios y retiros de efectivo en caja.
 *
 * @property int $id
 * @property string $uuid
 * @property int $empresa_id
 * @property int $sucursal_id
 * @property int $caja_turno_id
 * @property int $user_id
 * @property string $tipo
 * @property string $concepto
 * @property string $monto
 * @property string|null $motivo
 * @property string|null $comprobante_numero
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read CajaTurno $turno
 * @property-read User $usuario
 */
class CajaMovimiento extends Model
{
    use HasFactory;

    /**
     * La tabla asociada con el modelo en la base de datos.
     *
     * @var string
     */
    protected $table = 'caja_movimientos';

    /**
     * Los atributos asignables en masa.
     *
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'empresa_id',
        'sucursal_id',
        'caja_turno_id',
        'user_id',
        'tipo',
        'concepto',
        'monto',
        'motivo',
        'comprobante_numero',
    ];

    /**
     * Obtiene los casts para convertir tipos de datos nativos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
        ];
    }

    /**
     * Método de arranque del modelo para generar UUID automáticamente.
     */
    protected static function booted(): void
    {
        static::creating(function (CajaMovimiento $movimiento): void {
            if (empty($movimiento->uuid)) {
                $movimiento->uuid = (string) Str::uuid();
            }
        });
    }

    // =========================================================
    // RELACIONES ELOQUENT
    // =========================================================

    /**
     * Turno de caja en el que se registró el movimiento.
     *
     * @return BelongsTo<CajaTurno, $this>
     */
    public function turno(): BelongsTo
    {
        return $this->belongsTo(CajaTurno::class, 'caja_turno_id');
    }

    /**
     * Usuario responsable de registrar el movimiento financiero.
     *
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
