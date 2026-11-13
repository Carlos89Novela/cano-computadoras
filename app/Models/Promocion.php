<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Promocion extends Model
{
    protected $table = 'promociones';

    protected $fillable = [
        'empresa_id',
        'sucursal_id',
        'nombre',
        'codigo_cupon',
        'descripcion',
        'tipo',
        'valor_descuento',
        'precio_combo',
        'cantidad_requerida',
        'fecha_inicio',
        'fecha_fin',
        'dias_semana',
        'hora_inicio',
        'hora_fin',
        'solo_clientes_destacados',
        'puntos_extra_otorgados',
        'limite_usos',
        'usos_actuales',
        'activo',
    ];

    protected $casts = [
        'valor_descuento' => 'decimal:2',
        'precio_combo' => 'decimal:2',
        'fecha_inicio' => 'datetime',
        'fecha_fin' => 'datetime',
        'dias_semana' => 'array',
        'solo_clientes_destacados' => 'boolean',
        'activo' => 'boolean',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PromocionItem::class, 'promocion_id');
    }

    public function estaVigenteAhora(?Carbon $momento = null): bool
    {
        if (! $this->activo) {
            return false;
        }

        $ahora =$momento ?? now();

        if ($this->fecha_inicio !== null && $ahora->lt($this->fecha_inicio)) {
            return false;
        }
        if ($this->fecha_fin !== null && $ahora->gt($this->fecha_fin)) {
            return false;
        }
        if ($this->limite_usos !== null && $this->usos_actuales >=$this->limite_usos) {
            return false;
        }
        if (! empty($this->dias_semana) && is_array($this->dias_semana)) {
            $diaActual = (int)$ahora->dayOfWeek;
            $diasPermitidos = array_map('intval',$this->dias_semana);
            if (! in_array($diaActual,$diasPermitidos, true)) {
                return false;
            }
        }
        if ($this->hora_inicio !== null &&$this->hora_fin !== null) {
            $horaActual =$ahora->format('H:i:s');
            if ($horaActual <$this->hora_inicio || $horaActual >$this->hora_fin) {
                return false;
            }
        }

        return true;
    }
}