<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Modelo Eloquent que representa a un Proveedor en el sistema.
 *
 * Mapea la tabla 'proveedores' y encapsula las relaciones multi-empresa y
 * la trazabilidad de autoría (creación, edición y desactivación).
 *
 * @property int $id
 * @property int $empresa_id
 * @property string $codigo
 * @property string $nombre
 * @property string|null $razon_social
 * @property string|null $rfc
 * @property string|null $contacto
 * @property string|null $telefono
 * @property string|null $correo
 * @property string|null $direccion
 * @property string|null $notas
 * @property bool $activo
 * @property int|null $creado_por_id
 * @property int|null $actualizado_por_id
 * @property int|null $desactivado_por_id
 * @property Carbon|null $desactivado_at
 * @property string|null $motivo_desactivacion
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Proveedor extends Model
{
    /**
     * Nombre de la tabla asociada en la base de datos.
     * Se especifica explícitamente ya que la pluralización automática de Laravel
     * en español (proveedor -> proveedors) diferiría de 'proveedores'.
     *
     * @var string
     */
    protected $table = 'proveedores';

    /**
     * Atributos asignables en masa (Mass Assignment).
     * Solo las columnas listadas aquí pueden ser persistidas mediante create() o update().
     *
     * @var list<string>
     */
    protected $fillable = [
        'empresa_id',
        'codigo',
        'nombre',
        'razon_social',
        'rfc',
        'contacto',
        'telefono',
        'correo',
        'direccion',
        'notas',
        'activo',
        'creado_por_id',
        'actualizado_por_id',
        'desactivado_por_id',
        'desactivado_at',
        'motivo_desactivacion',
    ];

    /**
     * Define los tipos de datos nativos a los que deben convertirse los atributos del modelo.
     * En Laravel 11/12 se define a través de un método casts() en lugar de la propiedad $casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Convierte 1 o 0 de la base de datos a true/false nativo en PHP
            'activo' => 'boolean',
            // Convierte la marca de tiempo a una instancia inmutable Carbon para manejo de fechas
            'desactivado_at' => 'datetime',
        ];
    }

    /**
     * Relación: Empresa a la cual pertenece este proveedor.
     * Cada proveedor pertenece estrictamente a una única entidad comercial.
     *
     * @return BelongsTo<Empresa, $this>
     */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(
            Empresa::class,
            'empresa_id'
        );
    }

    /**
     * Relación: Usuario del sistema que dio de alta el registro del proveedor.
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
     * Relación: Último usuario que modificó los datos o información del proveedor.
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
     * Relación: Usuario que ejecutó la desactivación o baja lógica del proveedor.
     *
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
