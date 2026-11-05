<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExistenciaAlmacen extends Model
{
    // ---------------------------------------------------------
    // TABLA
    // Utiliza el nombre explícito porque la tabla representa
    // existencias consolidadas por producto y almacén.
    // ---------------------------------------------------------

    protected $table = 'existencias_almacen';

    // ---------------------------------------------------------
    // ASIGNACIÓN MASIVA
    // Define los atributos que pueden persistirse mediante
    // las acciones controladas del módulo de inventario.
    // ---------------------------------------------------------

    protected $fillable = [
        'empresa_id',
        'almacen_id',
        'producto_id',
        'cantidad',
        'cantidad_reservada',
        'costo_promedio',
        'actualizado_por_id',
    ];

    // ---------------------------------------------------------
    // CONVERSIÓN DE TIPOS
    // Conserva cuatro decimales para cantidades y costo
    // promedio sin perder precisión durante las operaciones.
    // ---------------------------------------------------------

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:4',
            'cantidad_reservada' => 'decimal:4',
            'costo_promedio' => 'decimal:4',
        ];
    }

    // ---------------------------------------------------------
    // EMPRESA
    // Relaciona el saldo con la empresa propietaria.
    // ---------------------------------------------------------

    /**
     * @return BelongsTo<Empresa, $this>
     */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(
            Empresa::class
        );
    }

    // ---------------------------------------------------------
    // ALMACÉN
    // Identifica el almacén físico o virtual que conserva
    // la existencia del producto.
    // ---------------------------------------------------------

    /**
     * @return BelongsTo<Almacen, $this>
     */
    public function almacen(): BelongsTo
    {
        return $this->belongsTo(
            Almacen::class
        );
    }

    // ---------------------------------------------------------
    // PRODUCTO
    // Identifica el producto al que corresponde el saldo.
    // ---------------------------------------------------------

    /**
     * @return BelongsTo<Producto, $this>
     */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(
            Producto::class
        );
    }

    // ---------------------------------------------------------
    // ÚLTIMO USUARIO
    // Relaciona la existencia con el usuario que realizó su
    // modificación consolidada más reciente.
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
    // MOVIMIENTOS
    // Recupera el historial de movimientos del mismo producto
    // dentro del mismo almacén.
    // ---------------------------------------------------------

    /**
     * @return HasMany<MovimientoInventario, $this>
     */
    public function movimientos(): HasMany
    {
        return $this->hasMany(
            MovimientoInventario::class,
            'almacen_id',
            'almacen_id'
        )
            ->whereColumn(
                'movimientos_inventario.producto_id',
                'existencias_almacen.producto_id'
            )
            ->whereColumn(
                'movimientos_inventario.empresa_id',
                'existencias_almacen.empresa_id'
            );
    }

    // ---------------------------------------------------------
    // CANTIDAD DISPONIBLE
    // Calcula el saldo que todavía puede utilizarse después
    // de descontar las unidades reservadas.
    // ---------------------------------------------------------

    public function cantidadDisponible(): string
    {
        return bcsub(
            (string) $this->cantidad,
            (string) $this->cantidad_reservada,
            4
        );
    }
}
