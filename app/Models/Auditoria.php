<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Modelo Eloquent que gestiona los registros inmutables de auditoría del sistema.
 *
 * Cada registro representa un evento relevante de negocio (creación, edición, cambio
 * de estado, asignación, inicio de sesión, etc.).
 *
 * Característica de Seguridad Crítica:
 * El modelo es de SOLO INSERCIÓN. La modificación (updating) y eliminación (deleting)
 * están bloqueadas a nivel de ciclo de vida del modelo (booted), garantizando la
 * integridad y no repudio del registro histórico para propósitos fiscales o periciales.
 *
 * @property int $id
 * @property int|null $usuario_id Actor que ejecutó la acción
 * @property int|null $usuario_afectado_id Usuario sobre el que recayó la acción (si aplica)
 * @property string|null $sesion_id ID de la sesión web
 * @property string $accion Identificador único de la acción (ej. proveedor.creado)
 * @property string $modulo Módulo funcional (proveedores, ordenes, usuarios, etc.)
 * @property string|null $modelo_tipo Nombre de la clase del modelo afectado (Polimórfico)
 * @property int|null $modelo_id ID del registro afectado
 * @property string $descripcion Resumen legible del evento
 * @property array<string, mixed>|null $valores_anteriores Estado antes del cambio (JSON)
 * @property array<string, mixed>|null $valores_nuevos Estado después del cambio (JSON)
 * @property array<string, mixed>|null $metadatos Información contextual adicional (JSON)
 * @property string|null $motivo Justificación obligatoria para acciones sensibles
 * @property string|null $direccion_ip Dirección IP cliente desde donde se originó el request
 * @property string|null $user_agent Agente de usuario/navegador del cliente
 * @property string|null $ruta Ruta HTTP invocada
 * @property string|null $metodo_http Verbo HTTP utilizado (POST, PUT, DELETE, etc.)
 * @property string|null $resultado Éxito o error del evento
 * @property Carbon $created_at Fecha y hora exacta del suceso
 */
class Auditoria extends Model
{
    /**
     * Desactiva la columna 'updated_at', ya que los eventos históricos nunca se modifican.
     */
    public const UPDATED_AT = null;

    /**
     * Nombre de la tabla en base de datos.
     *
     * @var string
     */
    protected $table = 'auditorias';

    /**
     * Atributos asignables en masa.
     *
     * @var list<string>
     */
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

    /**
     * Casteo de columnas JSON a arreglos nativos de PHP.
     *
     * @return array<string, string>
     */
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
     * Relación: Usuario que ejecutó la acción auditada.
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
     * Relación: Usuario objetivo o afectado directamente por la acción (ej. cambio de rol o permisos).
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

    /**
     * Intercepta eventos del modelo para garantizar inmutabilidad estricta.
     * Cualquier intento de modificar o eliminar un registro de auditoría lanzará una LogicException.
     */
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
