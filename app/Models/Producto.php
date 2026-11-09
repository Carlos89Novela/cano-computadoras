<?php

namespace App\Models;

// ---------------------------------------------------------
// IMPORTACIONES
// Modelos y tipos de relaciones de Eloquent utilizados por Producto.
// ---------------------------------------------------------

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Modelo representativo de un artículo en el catálogo maestro.
 *
 * @property int $id
 * @property int $empresa_id
 * @property int $categoria_producto_id
 * @property int|null $proveedor_principal_id
 * @property string $sku
 * @property string|null $codigo_barras
 * @property string $nombre
 * @property string|null $descripcion
 * @property string|null $marca
 * @property string|null $modelo
 * @property string $unidad_medida
 * @property string $costo_referencia
 * @property string $precio_base
 * @property bool $es_receta
 * @property string|null $clave_sat
 * @property string|null $clave_unidad_sat
 * @property bool $activo
 * @property int|null $creado_por_id
 * @property int|null $actualizado_por_id
 * @property int|null $desactivado_por_id
 * @property Carbon|null $desactivado_at
 * @property string|null $motivo_desactivacion
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Empresa $empresa
 * @property-read CategoriaProducto $categoria
 * @property-read Proveedor|null $proveedorPrincipal
 * @property-read User|null $creadoPor
 * @property-read User|null $actualizadoPor
 * @property-read User|null $desactivadoPor
 * @property-read ProductoReceta|null $receta
 * @property-read Collection<int, ExistenciaAlmacen> $existenciasAlmacen
 * @property-read Collection<int, MovimientoInventario> $movimientosInventario
 * @property-read Collection<int, ReservaInventario> $reservasInventario
 */
class Producto extends Model
{
    // ---------------------------------------------------------
    // CONFIGURACIÓN DE TABLA Y ASIGNACIÓN MASIVA
    // Define el nombre de la tabla y los atributos permitidos.
    // ---------------------------------------------------------

    protected $table = 'productos';

    protected $fillable = [
        'empresa_id',
        'categoria_producto_id',
        'proveedor_principal_id',
        'sku',
        'codigo_barras',
        'nombre',
        'descripcion',
        'marca',
        'modelo',
        'unidad_medida',
        'costo_referencia',
        'precio_base',
        'es_receta',
        'clave_sat',
        'clave_unidad_sat',
        'activo',
        'creado_por_id',
        'actualizado_por_id',
        'desactivado_por_id',
        'desactivado_at',
        'motivo_desactivacion',
    ];

    // ---------------------------------------------------------
    // CONVERSIONES DE TIPO (CASTS)
    // Asegura el casteo numérico, booleano y de fechas.
    // ---------------------------------------------------------

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'costo_referencia' => 'decimal:2',
            'precio_base' => 'decimal:2',
            'es_receta' => 'boolean',
            'activo' => 'boolean',
            'desactivado_at' => 'datetime',
        ];
    }

    // ---------------------------------------------------------
    // RELACIÓN: EMPRESA
    // Inquilino (Tenant) al que pertenece este producto.
    // ---------------------------------------------------------

    /**
     * @return BelongsTo<Empresa, $this>
     */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    // ---------------------------------------------------------
    // RELACIÓN: CATEGORÍA
    // Clasificación y agrupación funcional del catálogo.
    // ---------------------------------------------------------

    /**
     * @return BelongsTo<CategoriaProducto, $this>
     */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaProducto::class, 'categoria_producto_id');
    }

    // ---------------------------------------------------------
    // RELACIÓN: PROVEEDOR PRINCIPAL
    // Proveedor predeterminado para órdenes de compra y cotizaciones.
    // ---------------------------------------------------------

    /**
     * @return BelongsTo<Proveedor, $this>
     */
    public function proveedorPrincipal(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_principal_id');
    }

    // ---------------------------------------------------------
    // RELACIONES: AUDITORÍA DE USUARIOS
    // Rastreabilidad de creación, edición y baja del producto.
    // ---------------------------------------------------------

    /**
     * @return BelongsTo<User, $this>
     */
    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actualizado_por_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function desactivadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'desactivado_por_id');
    }

    // ---------------------------------------------------------
    // RELACIÓN: RECETA / ESCANDALLO (BOM)
    // Ficha técnica con la lista de insumos requeridos si es_receta = true.
    // ---------------------------------------------------------

    /**
     * @return HasOne<ProductoReceta, $this>
     */
    public function receta(): HasOne
    {
        return $this->hasOne(ProductoReceta::class, 'producto_id');
    }

    // ---------------------------------------------------------
    // RELACIÓN: EXISTENCIAS POR ALMACÉN
    // Balances consolidados de inventario en cada almacén de la empresa.
    // ---------------------------------------------------------

    /**
     * @return HasMany<ExistenciaAlmacen, $this>
     */
    public function existenciasAlmacen(): HasMany
    {
        return $this->hasMany(ExistenciaAlmacen::class, 'producto_id');
    }

    // ---------------------------------------------------------
    // RELACIÓN: MOVIMIENTOS DE INVENTARIO (KARDEX)
    // Bitácora histórica inmutable de entradas, salidas y traspasos.
    // ---------------------------------------------------------

    /**
     * @return HasMany<MovimientoInventario, $this>
     */
    public function movimientosInventario(): HasMany
    {
        return $this->hasMany(MovimientoInventario::class, 'producto_id');
    }

    // ---------------------------------------------------------
    // RELACIÓN: RESERVAS DE INVENTARIO
    // Existencias comprometidas para órdenes de servicio o pedidos pendientes.
    // ---------------------------------------------------------

    /**
     * @return HasMany<ReservaInventario, $this>
     */
    public function reservasInventario(): HasMany
    {
        return $this->hasMany(ReservaInventario::class, 'producto_id');
    }
}
