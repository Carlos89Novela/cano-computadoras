<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo de Suplencia Temporal de Rol.
 *
 * Registra la delegación temporal del rol `supervisor` a un colaborador
 * y almacena el `rol_anterior` para revertir el cambio al regresar el titular.
 */
class SuplenciaRol extends Model
{
    protected $table = 'suplencias_rol';

    protected $fillable = [
        'empresa_id',
        'supervisor_ausente_id',
        'suplente_id',
        'rol_anterior',
        'rol_temporal',
        'motivo',
        'fecha_inicio',
        'fecha_fin_estimada',
        'estado',
        'autorizado_por_id',
        'restaurado_por_id',
        'restaurado_at',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin_estimada' => 'date',
            'restaurado_at' => 'datetime',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function supervisorAusente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_ausente_id');
    }

    public function suplente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suplente_id');
    }

    public function autorizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autorizado_por_id');
    }

    public function restauradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'restaurado_por_id');
    }

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('estado', 'activa');
    }
}