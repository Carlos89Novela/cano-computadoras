<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class RegistroAcceso extends Model
{
    public const CREATED_AT = 'ocurrido_at';

    public const UPDATED_AT = null;

    protected $table = 'registros_acceso';

    protected $fillable = [
        'user_id',
        'correo_intentado',
        'sesion_id',
        'evento',
        'resultado',
        'direccion_ip',
        'user_agent',
        'ruta',
        'metodo_http',
        'motivo_fallo',
        'metadatos',
    ];

    protected function casts(): array
    {
        return [
            'metadatos' => 'array',
            'ocurrido_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'user_id'
        );
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException(
                'Los registros de acceso no pueden modificarse.'
            );
        });

        static::deleting(function (): never {
            throw new LogicException(
                'Los registros de acceso no pueden eliminarse.'
            );
        });
    }
}
