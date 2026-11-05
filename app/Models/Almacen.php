<?php

namespace App\Models;

// ---------------------------------------------------------
// IMPORTACIONES
// Modelo base y tipos de relaciones de Eloquent empleadas
// para asociar almacenes con empresas, sucursales, usuarios,
// existencias y movimientos.
// ---------------------------------------------------------

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Almacen extends Model
{
    // ---------------------------------------------------------
    // TIPOS DE ALMACÉN
    // Define las constantes de clasificación operativa para
    // almacenes físicos (principal, secundario, merma,
    // devoluciones) y virtuales (tránsito).
    // ---------------------------------------------------------

    public const TIPO_PRINCIPAL = 'principal';

    public const TIPO_SECUNDARIO = 'secundario';

    public const TIPO_TRANSITO = 'transito';

    public const TIPO_MERMA = 'merma';

    public const TIPO_DEVOLUCIONES = 'devoluciones';

    // ---------------------------------------------------------
    // TABLA
    // Nombre explícito de la tabla en base de datos.
    // ---------------------------------------------------------

    protected $table = 'almacenes';

    // ---------------------------------------------------------
    // ASIGNACIÓN MASIVA
    // Atributos permitidos al registrar o actualizar almacenes
    // mediante las acciones de dominio.
    // ---------------------------------------------------------

    protected $fillable = [
        'empresa_id',
        'sucursal_id',
        'codigo',
        'nombre',
        'tipo',
        'es_virtual',
        'permite_existencias',
        'activo',
        'creado_por_id',
        'actualizado_por_id',
        'desactivado_por_id',
        'desactivado_at',
        'motivo_desactivacion',
    ];

    // ---------------------------------------------------------
    // CONVERSIONES DE TIPO
    // Garantiza que los indicadores booleanos y fechas se
    // manipulen como tipos nativos de PHP.
    // ---------------------------------------------------------

    protected function casts(): array
    {
        return [
            'es_virtual' => 'boolean',
            'permite_existencias' => 'boolean',
            'activo' => 'boolean',
            'desactivado_at' => 'datetime',
        ];
    }

    // ---------------------------------------------------------
    // EMPRESA
    // Organización multi-tenant propietaria del almacén.
    // ---------------------------------------------------------

    /**
     * @return BelongsTo<Empresa, $this>
     */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    // ---------------------------------------------------------
    // SUCURSAL
    // Ubicación física a la que pertenece el almacén.
    // Los almacenes virtuales de tránsito no poseen sucursal.
    // ---------------------------------------------------------

    /**
     * @return BelongsTo<Sucursal, $this>
     */
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    // ---------------------------------------------------------
    // USUARIO CREADOR
    // Cuenta de usuario responsable del registro original.
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
    // Cuenta de usuario responsable de la última modificación.
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
    // Cuenta de usuario responsable de retirar el almacén.
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

    // ---------------------------------------------------------
    // VERIFICACIÓN DE TRÁNSITO
    // Determina si el almacén es el depósito virtual de
    // mercancía en movimiento entre sucursales.
    // ---------------------------------------------------------

    public function esTransito(): bool
    {
        return $this->tipo === self::TIPO_TRANSITO;
    }

    // ---------------------------------------------------------
    // VERIFICACIÓN DE ALMACÉN FÍSICO
    // Determina si el almacén corresponde a un espacio
    // tangible asignado a una sucursal física.
    // ---------------------------------------------------------

    public function esFisico(): bool
    {
        return ! $this->es_virtual;
    }

    // ---------------------------------------------------------
    // EXISTENCIAS EN EL ALMACÉN
    // Saldos consolidados de productos custodiados en este almacén.
    // ---------------------------------------------------------

    /**
     * @return HasMany<ExistenciaAlmacen, $this>
     */
    public function existencias(): HasMany
    {
        return $this->hasMany(
            ExistenciaAlmacen::class,
            'almacen_id'
        );
    }

    // ---------------------------------------------------------
    // MOVIMIENTOS DE INVENTARIO
    // Bitácora histórica inmutable de entradas, salidas y
    // transferencias originadas o recibidas en este almacén.
    // ---------------------------------------------------------

    /**
     * @return HasMany<MovimientoInventario, $this>
     */
    public function movimientosInventario(): HasMany
    {
        return $this->hasMany(
            MovimientoInventario::class,
            'almacen_id'
        );
    }

    // ---------------------------------------------------------
    // RESERVAS DE INVENTARIO
    // Recupera las reservas que comprometen existencias de
    // este almacén.
    // ---------------------------------------------------------

    /**
     * @return HasMany<ReservaInventario, $this>     */
    public function reservasInventario(): HasMany
    {
        return $this->hasMany(
            ReservaInventario::class,
            'almacen_id'
        );
    }
}
