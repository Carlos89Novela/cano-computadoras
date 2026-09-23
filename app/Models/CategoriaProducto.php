<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoriaProducto extends Model
{
    protected $table = 'categorias_producto';

    protected $fillable = [
        'empresa_id',
        'nombre',
        'descripcion',
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
            'activo' => 'boolean',
            'desactivado_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Empresa, $this>
     */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(
            Empresa::class
        );
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

    /**
     * @return BelongsTo<User, $this>
     */
    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'actualizado_por_id'
        );
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function desactivadoPor(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'desactivado_por_id'
        );
    }
}
