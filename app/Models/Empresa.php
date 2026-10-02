<?php

namespace App\Models;

// ---------------------------------------------------------
// IMPORTACIONES
// Modelo base de Eloquent, tipos de relaciones para agrupar
// sucursales, almacenes, catálogo, inventario y usuarios,
// además de utilidades de fecha.
// ---------------------------------------------------------

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
    // ---------------------------------------------------------
    // TABLA
    // Nombre explícito de la tabla en base de datos.
    // ---------------------------------------------------------

    protected $table = 'empresas';

    // ---------------------------------------------------------
    // ASIGNACIÓN MASIVA
    // Columnas permitidas al registrar o modificar la empresa.
    // ---------------------------------------------------------

    /**
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

    // ---------------------------------------------------------
    // CONVERSIONES DE TIPO
    // Conversiones de tipo automáticas para atributos del modelo.
    // ---------------------------------------------------------

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    // ---------------------------------------------------------
    // SUCURSALES
    // Sucursales físicas pertenecientes a la empresa.
    // ---------------------------------------------------------

    /**
     * @return HasMany<Sucursal, $this>
     */
    public function sucursales(): HasMany
    {
        return $this->hasMany(Sucursal::class);
    }

    // ---------------------------------------------------------
    // ALMACENES
    // Almacenes físicos o virtuales pertenecientes a la empresa.
    // ---------------------------------------------------------

    /**
     * @return HasMany<Almacen, $this>
     */
    public function almacenes(): HasMany
    {
        return $this->hasMany(Almacen::class);
    }

    // ---------------------------------------------------------
    // USUARIO CREADOR
    // Cuenta de usuario que registró la empresa.
    // ---------------------------------------------------------

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

    // ---------------------------------------------------------
    // ÚLTIMO USUARIO ACTUALIZADOR
    // Cuenta del último usuario que modificó los datos de la empresa.
    // ---------------------------------------------------------

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

    // ---------------------------------------------------------
    // CATEGORÍAS DE PRODUCTOS
    // Clasificaciones de catálogo pertenecientes a la empresa.
    // ---------------------------------------------------------

    /**
     * @return HasMany<CategoriaProducto, $this>
     */
    public function categoriasProducto(): HasMany
    {
        return $this->hasMany(
            CategoriaProducto::class,
            'empresa_id'
        );
    }

    // ---------------------------------------------------------
    // PROVEEDORES
    // Proveedores comerciales registrados para la empresa.
    // ---------------------------------------------------------

    /**
     * @return HasMany<Proveedor, $this>
     */
    public function proveedores(): HasMany
    {
        return $this->hasMany(
            Proveedor::class,
            'empresa_id'
        );
    }

    // ---------------------------------------------------------
    // PRODUCTOS
    // Catálogo de productos perteneciente a la empresa.
    // ---------------------------------------------------------

    /**
     * @return HasMany<Producto, $this>
     */
    public function productos(): HasMany
    {
        return $this->hasMany(
            Producto::class,
            'empresa_id'
        );
    }

    // ---------------------------------------------------------
    // EXISTENCIAS EN ALMACENES
    // Saldos consolidados de inventario pertenecientes a la empresa.
    // ---------------------------------------------------------

    /**
     * @return HasMany<ExistenciaAlmacen, $this>
     */
    public function existenciasAlmacen(): HasMany
    {
        return $this->hasMany(
            ExistenciaAlmacen::class,
            'empresa_id'
        );
    }

    // ---------------------------------------------------------
    // MOVIMIENTOS DE INVENTARIO
    // Bitácora de movimientos de inventario de la empresa.
    // ---------------------------------------------------------

    /**
     * @return HasMany<MovimientoInventario, $this>
     */
    public function movimientosInventario(): HasMany
    {
        return $this->hasMany(
            MovimientoInventario::class,
            'empresa_id'
        );
    }

    // ---------------------------------------------------------
    // RESERVAS DE INVENTARIO
    // Recupera todas las reservas de inventario pertenecientes
    // a la empresa.
    // ---------------------------------------------------------

    /**
     * @return HasMany<ReservaInventario, $this>
     */
    public function reservasInventario(): HasMany
    {
        return $this->hasMany(
            ReservaInventario::class
        );
    }
}
