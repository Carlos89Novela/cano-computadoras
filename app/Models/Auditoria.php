<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class Auditoria extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'auditorias';

    protected $fillable = [
        'usuario_id',
        'usuario_afectado_id',
        'sesion_id',
        'accion',
        'modulo',
        'modelo_tipo',
        'modelo_id',
        'descripcion',
        'valores_anteriores',
        'valores_nuevos',
        'metadatos',
        'motivo',
        'direccion_ip',
        'user_agent',
        'ruta',
        'metodo_http',
        'resultado',
    ];

    protected function casts(): array
    {
        return [
            'valores_anteriores' => 'array',
            'valores_nuevos' => 'array',
            'metadatos' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Usuario que realizó la acción.
     *
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'usuario_id'
        );
    }

    /**
     * Usuario afectado por la acción.
     *
     * @return BelongsTo<User, $this>
     */
    public function usuarioAfectado(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'usuario_afectado_id'
        );
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException(
                'Los registros de auditoría no pueden modificarse.'
            );
        });

        static::deleting(function (): never {
            throw new LogicException(
                'Los registros de auditoría no pueden eliminarse.'
            );
        });
    }
}
