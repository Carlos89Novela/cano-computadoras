<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sucursal extends Model
{
    protected $table = 'sucursales';

    protected $fillable = [
        'empresa_id',
        'codigo',
        'nombre',
        'telefono',
        'correo',
        'direccion',
        'ciudad',
        'estado',
        'codigo_postal',
        'es_principal',
        'activo',
        'creado_por_id',
        'actualizado_por_id',
        'desactivado_por_id',
        'desactivado_at',
        'motivo_desactivacion',
    ];

    protected function casts(): array
    {
        return [
            'es_principal' => 'boolean',
            'activo' => 'boolean',
            'desactivado_at' => 'datetime',
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
     * @return HasMany<Almacen, $this>
     */
    public function almacenes(): HasMany
    {
        return $this->hasMany(Almacen::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'sucursal_usuario'
        )
            ->withPivot([
                'es_principal',
                'es_gerente',
                'activo',
                'asignado_por_id',
                'asignado_at',
                'finalizado_at',
            ])
            ->withTimestamps();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'creado_por_id'
        );
    }
}
