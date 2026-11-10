<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $empresa_id
 * @property int $sucursal_id
 * @property int|null $restaurante_mesa_id
 * @property string $folio
 * @property string $cliente_nombre
 * @property string|null $cliente_telefono
 * @property string|null $cliente_email
 * @property int $numero_personas
 * @property Carbon $fecha_hora_reservacion
 * @property int $duracion_estimada_minutos
 * @property string $estado
 * @property string|null $notas_especiales
 * @property int|null $atendido_por_user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Empresa $empresa
 * @property-read Sucursal $sucursal
 * @property-read RestauranteMesa|null $mesa
 * @property-read User|null $atendidoPor
 */
class RestauranteReservacion extends Model
{
    protected $table = 'restaurante_reservaciones';

    protected $fillable = [
        'empresa_id',
        'sucursal_id',
        'restaurante_mesa_id',
        'folio',
        'cliente_nombre',
        'cliente_telefono',
        'cliente_email',
        'numero_personas',
        'fecha_hora_reservacion',
        'duracion_estimada_minutos',
        'estado',
        'notas_especiales',
        'atendido_por_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'numero_personas' => 'integer',
            'fecha_hora_reservacion' => 'datetime',
            'duracion_estimada_minutos' => 'integer',
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
     * @return BelongsTo<Sucursal, $this>
     */
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    /**
     * @return BelongsTo<RestauranteMesa, $this>
     */
    public function mesa(): BelongsTo
    {
        return $this->belongsTo(RestauranteMesa::class, 'restaurante_mesa_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function atendidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'atendido_por_user_id');
    }
}
