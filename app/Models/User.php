<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Spatie\Permission\Traits\HasRoles;

/**
 * Modelo Eloquent que representa a los Usuarios del sistema (Clientes, Empleados, Supervisores, Administradores y Propietario).
 *
 * Implementa autenticación Laravel, verificación de correo electrónico obligatoria (MustVerifyEmail)
 * y el trait HasRoles de Spatie Permission para gestión de roles y permisos delegables.
 *
 * Jerarquía de Propietario (Owner):
 * El atributo 'es_propietario' designa al superusuario de la organización, quien posee de forma
 * exclusiva los permisos reservados (asignación de roles administrativos, modificación de otros propietarios
 * y descarga de auditorías completas) que no pueden ser delegados a administradores comunes.
 *
 * @property int $id
 * @property string $name Nombre completo del usuario
 * @property string $email Correo electrónico único
 * @property string $password Contraseña cifrada
 * @property bool $es_propietario Indicador de máxima jerarquía en el sistema
 * @property Carbon|null $email_verified_at Fecha de verificación de correo
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int $id
 * @property int|null $empresa_id
 * @property-read Empresa|null $empresa
 * @property-read Sucursal|null $sucursal
 */
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * Columnas asignables en masa.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'es_propietario',
    ];

    /**
     * Atributos ocultos en serialización JSON (APIs o vistas).
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Conversiones automáticas de tipos de atributos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'es_propietario' => 'boolean',
        ];
    }

    /**
     * Relación: Equipos registrados pertenecientes a este cliente.
     *
     * @return HasMany<Equipo, $this>
     */
    public function equipos(): HasMany
    {
        return $this->hasMany(Equipo::class);
    }

    /**
     * Relación: Órdenes de servicio registradas a nombre de este cliente.
     *
     * @return HasMany<OrdenServicio, $this>
     */
    public function ordenesServicio(): HasMany
    {
        return $this->hasMany(OrdenServicio::class);
    }

    /**
     * Relación: Órdenes de servicio donde el usuario fue asignado como empleado técnico.
     *
     * @return HasMany<OrdenAsignacion, $this>
     */
    public function asignacionesComoEmpleado(): HasMany
    {
        return $this->hasMany(
            OrdenAsignacion::class,
            'empleado_id'
        );
    }

    /**
     * Relación: Asignaciones de trabajo emitidas o despachadas por este supervisor/administrador.
     *
     * @return HasMany<OrdenAsignacion, $this>
     */
    public function asignacionesRealizadas(): HasMany
    {
        return $this->hasMany(
            OrdenAsignacion::class,
            'asignado_por_id'
        );
    }

    /**
     * Comprueba si el usuario tiene el estatus de propietario del sistema.
     */
    public function esPropietario(): bool
    {
        return $this->es_propietario === true;
    }

    /**
     * Relación: Sucursales a las que está asignado el usuario para trabajar.
     *
     * @return BelongsToMany<Sucursal, $this>
     */
    public function sucursales(): BelongsToMany
    {
        return $this->belongsToMany(
            Sucursal::class,
            'sucursal_usuario'
        )
            ->withPivot([
                'es_principal',
                'es_gerente',
                'activo',
                'asignado_por_id',
                'asignado_at',
                'finalizado_at',
            ])
            ->withTimestamps();
    }

    /**
     * Comprueba si el usuario está asignado y activo en una sucursal determinada.
     */
    public function perteneceASucursal(
        Sucursal $sucursal
    ): bool {
        return $this->sucursales()
            ->whereKey($sucursal->id)
            ->wherePivot('activo', true)
            ->exists();
    }

    /**
     * Retorna la lista de IDs de sucursales que el usuario puede operar.
     * Propietarios y Administradores globales tienen alcance total sobre la empresa.
     *
     * @return list<int>
     */
    public function sucursalesOperativasIds(int $empresaId): array
    {
        if ($this->esPropietario() || ($this->hasRole('administrador') && ! $this->sucursales()->exists())) {
            /** @var list<int> */
            return Sucursal::query()
                ->where('empresa_id', $empresaId)
                ->where('activo', true)
                ->pluck('id')
                ->all();
        }

        /** @var list<int> */
        return $this->sucursales()
            ->where('sucursales.empresa_id', $empresaId)
            ->where('sucursales.activo', true)
            ->wherePivot('activo', true)
            ->pluck('sucursales.id')
            ->all();
    }

    /**
     * Determina si el usuario tiene permiso operativo sobre un almacén físico.
     */
    public function puedeGestionarAlmacen(Almacen $almacen): bool
    {
        if ($almacen->es_virtual) {
            return true;
        }

        if ($almacen->sucursal_id === null) {
            return false;
        }

        return in_array($almacen->sucursal_id, $this->sucursalesOperativasIds($almacen->empresa_id), true);
    }

    /**
     * @return HasMany<ExistenciaAlmacen, $this>
     */
    public function existenciasActualizadas(): HasMany
    {
        return $this->hasMany(
            ExistenciaAlmacen::class,
            'actualizado_por_id'
        );
    }

    /**
     * @return HasMany<MovimientoInventario, $this>
     */
    public function movimientosInventario(): HasMany
    {
        return $this->hasMany(
            MovimientoInventario::class,
            'usuario_id'
        );
    }

    // ---------------------------------------------------------
    // RESERVAS CREADAS
    // Recupera las reservas de inventario creadas originalmente
    // por este usuario.
    // ---------------------------------------------------------

    /**
     * @return HasMany<ReservaInventario, $this>
     */
    public function reservasInventarioCreadas(): HasMany
    {
        return $this->hasMany(
            ReservaInventario::class,
            'reservado_por_id'
        );
    }

    // ---------------------------------------------------------
    // RESERVAS ACTUALIZADAS
    // Recupera las reservas cuyo último consumoio liberación
    // fue realizado por este usuario.
    // ---------------------------------------------------------

    /**
     * @return HasMany<ReservaInventario, $this>     */
    public function reservasInventarioActualizadas(): HasMany
    {
        return $this->hasMany(
            ReservaInventario::class,
            'actualizado_por_id'
        );
    }
}
