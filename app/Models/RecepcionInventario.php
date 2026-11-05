<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Encabezado de una recepción múltiple de inventario.
 *
 * @property int $id
 * @property int $empresa_id
 * @property int $almacen_id
 * @property int|null $proveedor_id
 * @property string $grupo_id
 * @property string $folio
 * @property string $tipo
 * @property string|null $referencia_externa
 * @property string $motivo
 * @property int $total_lineas
 * @property string $importe_total
 * @property int|null $recibido_por_id
 * @property Carbon $recibido_at
 * @property-read Empresa $empresa
 * @property-read Almacen $almacen
 * @property-read Proveedor|null $proveedor
 * @property-read User|null $recibidoPor
 * @property-read Collection<int, RecepcionInventarioDetalle> $detalles
 */
class RecepcionInventario extends Model
{
    // ---------------------------------------------------------
    // TIPOS DE RECEPCIÓN
    // Centraliza los orígenes administrativos permitidos para
    // las entradas múltiples de inventario.
    // ---------------------------------------------------------

    public const TIPO_COMPRA = 'compra';

    public const TIPO_RECEPCION = 'recepcion';

    public const TIPO_DEVOLUCION = 'devolucion';

    public const TIPO_AJUSTE_POSITIVO = 'ajuste_positivo';

    // ---------------------------------------------------------
    // TABLA
    // Declara explícitamente la tabla utilizada por el modelo.
    // ---------------------------------------------------------

    protected $table = 'recepciones_inventario';

    // ---------------------------------------------------------
    // ASIGNACIÓN MASIVA
    // Limita los campos que puede establecer la acción de
    // recepción múltiple.
    // ---------------------------------------------------------

    protected $fillable = [
        'empresa_id',
        'almacen_id',
        'proveedor_id',
        'grupo_id',
        'folio',
        'tipo',
        'referencia_externa',
        'motivo',
        'total_lineas',
        'importe_total',
        'recibido_por_id',
        'recibido_at',
    ];

    // ---------------------------------------------------------
    // CONVERSIONES
    // Mantiene precisión monetaria y convierte la fecha de
    // recepción en una instancia de Carbon.
    // ---------------------------------------------------------

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_lineas' => 'integer',
            'importe_total' => 'decimal:4',
            'recibido_at' => 'datetime',
        ];
    }

    // ---------------------------------------------------------
    // EMPRESA
    // Identifica a la empresa propietaria del documento.
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
    // Identifica el almacén de destino de todas las líneas.
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
    // PROVEEDOR
    // Relaciona opcionalmente la recepción con el proveedor
    // que entregó los productos.
    // ---------------------------------------------------------

    /**
     * @return BelongsTo<Proveedor, $this>
     */
    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(
            Proveedor::class
        );
    }

    // ---------------------------------------------------------
    // USUARIO RECEPTOR
    // Identifica al usuario que confirmó la recepción.
    // ---------------------------------------------------------

    /**
     * @return BelongsTo<User, $this>
     */
    public function recibidoPor(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'recibido_por_id'
        );
    }

    // ---------------------------------------------------------
    // DETALLES
    // Recupera todos los productos incluidos en el documento.
    // ---------------------------------------------------------

    /**
     * @return HasMany<RecepcionInventarioDetalle, $this>
     */
    public function detalles(): HasMany
    {
        return $this->hasMany(
            RecepcionInventarioDetalle::class,
            'recepcion_inventario_id'
        );
    }

    // ---------------------------------------------------------
    // TIPOS PERMITIDOS
    // Proporciona la lista que utilizarán las validaciones y
    // los formularios.
    // ---------------------------------------------------------

    /**
     * @return list<string>
     */
    public static function tiposPermitidos(): array
    {
        return [
            self::TIPO_COMPRA,
            self::TIPO_RECEPCION,
            self::TIPO_DEVOLUCION,
            self::TIPO_AJUSTE_POSITIVO,
        ];
    }
}
