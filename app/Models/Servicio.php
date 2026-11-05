<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Modelo Eloquent para el catálogo de Servicios Técnicos ofrecidos por el taller.
 *
 * Ejemplos: Formateo e instalación de SO, Mantenimiento preventivo, Reballing,
 * Cambio de pantalla, etc.
 *
 * @property int $id
 * @property string $nombre Nombre del servicio técnico
 * @property string|null $descripcion Detalles del trabajo incluido
 * @property string $precio Costo base sugerido del servicio (decimal:2)
 * @property bool $activo Disponibilidad del servicio en ventanilla y catálogo web
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Servicio extends Model
{
    /**
     * Nombre explícito de la tabla en base de datos.
     *
     * @var string
     */
    protected $table = 'servicios';

    /**
     * Columnas asignables en masa.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
        'descripcion',
        'precio',
        'activo',
    ];

    /**
     * Conversiones automáticas de tipos de atributos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'precio' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    /**
     * Relación: Órdenes de servicio que contrataron este servicio base.
     *
     * @return HasMany<OrdenServicio, $this>
     */
    public function ordenesServicio(): HasMany
    {
        return $this->hasMany(OrdenServicio::class);
    }
}
