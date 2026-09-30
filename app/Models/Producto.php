<?php

namespace App\Models;

// ---------------------------------------------------------
// IMPORTACIONES
// Relaciones utilizadas para empresa, categoría, proveedor
// y usuarios responsables de los cambios.
// ---------------------------------------------------------

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Producto extends Model
{
    // ---------------------------------------------------------
    // CONFIGURACIÓN DEL MODELO
    // Define la tabla y los campos que pueden asignarse.
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
        'activo',
        'creado_por_id',
        'actualizado_por_id',
        'desactivado_por_id',
        'desactivado_at',
        'motivo_desactivacion',
    ];

    // ---------------------------------------------------------
    // CONVERSIONES DE TIPO
    // Conserva precios con dos decimales y fechas como objetos.
    // ---------------------------------------------------------

    protected function casts(): array
    {
        return [
            'costo_referencia' => 'decimal:2',
            'precio_base' => 'decimal:2',
            'activo' => 'boolean',
            'desactivado_at' => 'datetime',
        ];
    }

    // ---------------------------------------------------------
    // EMPRESA
    // Organización propietaria del producto.
    // ---------------------------------------------------------

    /**
     * @return BelongsTo<Empresa, $this>
     */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(
            Empresa::class,
            'empresa_id'
        );
    }

    // ---------------------------------------------------------
    // CATEGORÍA
    // Clasificación principal del producto.
    // ---------------------------------------------------------

    /**
     * @return BelongsTo<CategoriaProducto, $this>
     */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(
            CategoriaProducto::class,
            'categoria_producto_id'
        );
    }

    // ---------------------------------------------------------
    // PROVEEDOR PRINCIPAL
    // Proveedor sugerido para compras y reabastecimiento.
    // Puede ser nulo.
    // ---------------------------------------------------------

    /**
     * @return BelongsTo<Proveedor, $this>
     */
    public function proveedorPrincipal(): BelongsTo
    {
        return $this->belongsTo(
            Proveedor::class,
            'proveedor_principal_id'
        );
    }

    // ---------------------------------------------------------
    // USUARIO CREADOR
    // Cuenta que registró originalmente el producto.
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
    // Cuenta responsable de la última modificación.
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
    // USUARIO QUE DESACTIVÓ
    // Cuenta responsable de retirar el producto del catálogo.
    // ---------------------------------------------------------

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
