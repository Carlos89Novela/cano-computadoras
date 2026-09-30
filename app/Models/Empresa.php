<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Modelo Eloquent que representa una Empresa (Entidad raíz multi-tenant).
 *
 * En Cano Computadoras, una Empresa agrupa sus propias sucursales, almacenes,
 * catálogo de categorías de productos y proveedores de manera aislada.
 *
 * @property int $id
 * @property string $nombre Nombre comercial de la empresa
 * @property string|null $razon_social Razón social legal
 * @property string|null $rfc Registro fiscal
 * @property string|null $telefono Teléfono corporativo
 * @property string|null $correo Correo corporativo
 * @property string|null $direccion_fiscal Domicilio fiscal
 * @property bool $activo Estado operativo de la empresa
 * @property int|null $creado_por_id
 * @property int|null $actualizado_por_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Empresa extends Model
{
    /**
     * Nombre explícito de la tabla en base de datos.
     *
     * @var string
     */
    protected $table = 'empresas';

    /**
     * Columnas asignables en masa.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
        'razon_social',
        'rfc',
        'telefono',
        'correo',
        'direccion_fiscal',
        'activo',
        'creado_por_id',
        'actualizado_por_id',
    ];

    /**
     * Conversiones de tipo automáticas.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    /**
     * Relación: Sucursales físicas pertenecientes a la empresa.
     *
     * @return HasMany<Sucursal, $this>
     */
    public function sucursales(): HasMany
    {
        return $this->hasMany(Sucursal::class);
    }

    /**
     * Relación: Almacenes (físicos o virtuales) pertenecientes a la empresa.
     *
     * @return HasMany<Almacen, $this>
     */
    public function almacenes(): HasMany
    {
        return $this->hasMany(Almacen::class);
    }

    /**
     * Relación: Usuario que creó la empresa.
     *
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
     * Relación: Último usuario que modificó los datos de la empresa.
     *
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
     * Relación: Categorías de productos pertenecientes a la empresa.
     *
     * @return HasMany<CategoriaProducto, $this>
     */
    public function categoriasProducto(): HasMany
    {
        return $this->hasMany(
            CategoriaProducto::class,
            'empresa_id'
        );
    }

    /**
     * Relación: Proveedores pertenecientes a la empresa.
     *
     * @return HasMany<Proveedor, $this>
     */
    public function proveedores(): HasMany
    {
        return $this->hasMany(
            Proveedor::class,
            'empresa_id'
        );
    }
}
