<?php

namespace App\Models;

// ---------------------------------------------------------
// IMPORTACIONES DE ELOQUENT
// ---------------------------------------------------------
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoriaProducto extends Model
{
    /**
     * La tabla asociada con el modelo en la base de datos.
     *
     * @var string
     */
    protected $table = 'categorias_producto';

    /**
     * Los atributos que son asignables en masa (Mass Assignment).
     * Incluye 'estacion_cocina' para el filtrado en pantallas KDS.
     *
     * @var list<string>
     */
    protected $fillable = [
        'empresa_id',
        'nombre',
        'estacion_cocina', // cocina, postres, parrilla, barra
        'descripcion',
        'activo',
        'creado_por_id',
        'actualizado_por_id',
        'desactivado_por_id',
        'desactivado_at',
        'motivo_desactivacion',
    ];

    /**
     * Obtiene los casts para convertir tipos de datos nativos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'desactivado_at' => 'datetime',
        ];
    }

    // =========================================================
    // RELACIONES ELOQUENT
    // =========================================================

    /**
     * Empresa matriz a la que pertenece la categoría.
     *
     * @return BelongsTo<Empresa, $this>
     */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /**
     * Usuario que registró originalmente la categoría.
     *
     * @return BelongsTo<User, $this>
     */
    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por_id');
    }

    /**
     * Usuario que realizó la última actualización de la categoría.
     *
     * @return BelongsTo<User, $this>
     */
    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actualizado_por_id');
    }

    /**
     * Usuario responsable de la desactivación de la categoría.
     *
     * @return BelongsTo<User, $this>
     */
    public function desactivadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'desactivado_por_id');
    }

    /**
     * Listado de productos e insumos clasificados en esta categoría.
     *
     * @return HasMany<Producto, $this>
     */
    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class, 'categoria_producto_id');
    }
}
