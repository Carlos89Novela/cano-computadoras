<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class MovimientoInventario extends Model
{
    // ---------------------------------------------------------
    // TIPOS DE MOVIMIENTOS
    // Centraliza las claves permitidas para las operaciones
    // actuales y las transferencias futuras.
    // ---------------------------------------------------------

    public const TIPO_ENTRADA = 'entrada';

    public const TIPO_SALIDA = 'salida';

    public const TIPO_AJUSTE_POSITIVO = 'ajuste_positivo';

    public const TIPO_AJUSTE_NEGATIVO = 'ajuste_negativo';

    public const TIPO_CONSUMO = 'consumo';

    public const TIPO_DEVOLUCION = 'devolucion';

    public const TIPO_TRANSFERENCIA_SALIDA =
        'transferencia_salida';

    public const TIPO_TRANSFERENCIA_TRANSITO =
        'transferencia_transito';

    public const TIPO_TRANSFERENCIA_ENTRADA =
        'transferencia_entrada';

    // ---------------------------------------------------------
    // MARCAS DE TIEMPO
    // Los movimientos solo tienen created_at porque son una
    // bitácora inmutable que nunca debe actualizarse.
    // ---------------------------------------------------------

    public const UPDATED_AT = null;

    protected $table = 'movimientos_inventario';

    // ---------------------------------------------------------
    // ASIGNACIÓN MASIVA
    // Permite crear movimientos únicamente con la información
    // definida por las acciones de inventario.
    // ---------------------------------------------------------

    protected $fillable = [
        'empresa_id',
        'almacen_id',
        'producto_id',
        'grupo_id',
        'folio',
        'tipo',
        'cantidad',
        'existencia_anterior',
        'existencia_nueva',
        'costo_unitario',
        'referencia_tipo',
        'referencia_id',
        'motivo',
        'usuario_id',
        'ocurrido_at',
    ];

    // ---------------------------------------------------------
    // CONVERSIÓN DE TIPOS
    // Mantiene precisión decimal y convierte la fecha operativa
    // en una instancia de fecha de Laravel.
    // ---------------------------------------------------------

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:4',
            'existencia_anterior' => 'decimal:4',
            'existencia_nueva' => 'decimal:4',
            'costo_unitario' => 'decimal:4',
            'ocurrido_at' => 'datetime',
        ];
    }

    // ---------------------------------------------------------
    // EMPRESA
    // Identifica la empresa propietaria del movimiento.
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
    // Identifica el almacén afectado por el movimiento.
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
    // Identifica el producto cuya existencia fue modificada.
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
    // USUARIO
    // Identifica al usuario responsable de la operación.
    // ---------------------------------------------------------

    /**
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'usuario_id'
        );
    }

    // ---------------------------------------------------------
    // INMUTABILIDAD
    // Los movimientos no pueden modificarse ni eliminarse una
    // vez registrados en la bitácora de inventario.
    // ---------------------------------------------------------

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException(
                'Los movimientos de inventario no pueden modificarse.'
            );
        });

        static::deleting(function (): never {
            throw new LogicException(
                'Los movimientos de inventario no pueden eliminarse.'
            );
        });
    }
}
