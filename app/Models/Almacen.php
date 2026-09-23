<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Almacen extends Model
{
    public const TIPO_PRINCIPAL = 'principal';

    public const TIPO_SECUNDARIO = 'secundario';

    public const TIPO_TRANSITO = 'transito';

    public const TIPO_MERMA = 'merma';

    public const TIPO_DEVOLUCIONES = 'devoluciones';

    protected $table = 'almacenes';

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

    protected function casts(): array
    {
        return [
            'es_virtual' => 'boolean',
            'permite_existencias' => 'boolean',
            'activo' => 'boolean',
            'desactivado_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Empresa, $this>
     */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /**
     * @return BelongsTo<Sucursal, $this>
     */
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function esTransito(): bool
    {
        return $this->tipo === self::TIPO_TRANSITO;
    }

    public function esFisico(): bool
    {
        return ! $this->es_virtual;
    }
}
